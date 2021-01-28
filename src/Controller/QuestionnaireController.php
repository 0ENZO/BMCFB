<?php

namespace App\Controller;

use App\Entity\Questionnaire;
use App\Entity\Record;
use App\Entity\Statement;
use App\Entity\Topic;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/questionnaire")
 */
class QuestionnaireController extends AbstractController
{
    /**
     * @Route("/{slug}", name="questionnaire_show")
     */
    public function index($slug): Response
    {
        $user = $this->getUser();
        $records = $this->getDoctrine()->getRepository(Record::class)->findByUser($user);
        $questionnaire = $this->getDoctrine()->getRepository(Questionnaire::class)->findOneBy(['slug' => $slug]);  
        $finished = false;

        $next = $this->nextTopic($questionnaire);
        if ($next == null){
            $finished = true;
        }

        return $this->render('questionnaire/index.html.twig', [
            'user' => $user,
            'records' => $records,
            'questionnaire' => $questionnaire,
            'next' => $next,
            'finished' => $finished
        ]); 
    }

    /**
     * @Route("/play/{id}", name="questionnaire_play")
     */
    public function play($id, Request $request): Response
    {
        $user = $this->getUser();
        $topic = $this->getDoctrine()->getRepository(Topic::class)->findOneBy(['id' => $id]);
        $statements = $this->getDoctrine()->getRepository(Statement::class)->findBy(['topic' => $topic]);  
        $questionnaire = $topic->getQuestionnaire();
        $em = $this->getDoctrine()->getManager();

        $formBuilder = $this->createFormBuilder($statements);
        foreach($statements as $key => $val){
            $formBuilder->add('record'.$key, IntegerType::class, [
                'label' => false,
                'attr' => [
                    'min' => 0,
                    'max'=> 6,
                    'step' => 2,
                    'placeholder' => '0 à 6',
                    'class' => 'text-center'
                ]
            ]);

        }

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            for($i = 0; $i < count($statements); $i++){
                $record = new Record();
                $record->setUser($user);
                $record->setStatement($statements[$i]);
                $record->setRate($form->getData()['record'.$i]);
                $record->setDate(new \DateTime());

                $em->persist($record);
            }

            $em->flush();
            $next = $this->nextTopic($questionnaire);

            if ($next == null){
                // return bilan 
            }else{
                return $this->redirectToRoute('questionnaire_play', [
                    'id' => $next->getId(),
                ]);
            }
        }
        
        return $this->render('questionnaire/play.html.twig', [
            'user' => $user,
            'topic' => $topic, 
            'statements' => $statements,
            'form' => $form->createView()
        ]); 
    }

    private function nextTopic($questionnaire)
    {
        $user = $this->getUser();
        $records = $this->getDoctrine()->getRepository(Record::class)->findByUser($user);
        $topics = $this->getDoctrine()->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $next = null;

        if($records){
            $index = count($records) / count($this->getDoctrine()->getRepository(Statement::class)->findBy(['topic' => $topics[1]]));
            if($index < count($topics)){
                $next = $topics[$index+1];
            }
        }else{
            $next = $topics[0];
        }
        return $next;
    }
}

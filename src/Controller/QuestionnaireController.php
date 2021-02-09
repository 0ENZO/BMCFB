<?php

namespace App\Controller;

use App\Entity\Topic;
use App\Entity\Record;
use App\Entity\Statement;
use App\Entity\Questionnaire;
use App\Repository\RecordRepository;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/questionnaire")
 * @IsGranted("ROLE_USER")
 */
class QuestionnaireController extends AbstractController
{
    /**
     * @Route("/{slug}", name="questionnaire_show")
     */
    public function index($slug): Response
    {
        $user = $this->getUser();
        $questionnaire = $this->getDoctrine()->getRepository(Questionnaire::class)->findOneBy(['slug' => $slug]);  
        $finished = false;
        $records = $this->getRecordsFromQuestionnaire($questionnaire);

        try {
            $next = $this->nextTopic($questionnaire);
            if ($next == null){
                $finished = true;
            }
        } catch (Exception $e) {
            //$this->addFlash('warning', $e->getMessage());
            return $this->render('questionnaire/index.html.twig', [
                'user' => $user,
                'questionnaire' => $questionnaire,
                'exception' => $e->getMessage()
            ]); 
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
                return $this->render('questionnaire/index.html.twig', [
                    'finished' => true,
                    'questionnaire' => $questionnaire
                ]);
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

        if ($topics){
            if($records){
                $index = count($records) / count($this->getDoctrine()->getRepository(Statement::class)->findBy(['topic' => $topics[0]]));
                if($index < count($topics)){
                    $next = $topics[$index];
                }
            }else{
                $next = $topics[0];
            }
        }else{
            throw new \Exception("Le questionnaire est en cours de rédaction, revenez plus tard.");
        }

        return $next;
    }

    /**
     * Calculate results and display resulsts (canva + text)
     * @Route("/{slug}/bilan", name="questionnaire_bilan")
     */
    public function bilan($slug)
    {
        $user = $this->getUser();

        return $this->render('questionnaire/bilan.html.twig', [

        ]);
    }

    /**
     * Delete all user records from the questionnaire
     * @Route("/{slug}/reset", name="questionnaire_reset")
     */
    public function reset($slug, RecordRepository $recordRepository)
    {
        $user = $this->getUser();
        $questionnaire = $this->getDoctrine()->getRepository(Questionnaire::class)->findOneBySlug($slug);
        $em = $this->getDoctrine()->getManager();

        $topics = $this->getDoctrine()->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);

        foreach ($topics as $topic){
            $statements = $this->getDoctrine()->getRepository(Statement::class)->findBy(['topic' => $topic]);
            foreach ($statements as $statement){
                $records = $recordRepository->findByStatementAndUser($statement, $user);
                foreach ($records as $record){
                    $em->remove($record);
                }
            }
        }
        $em->flush();

        return $this->redirectToRoute('questionnaire_show', [
            'slug' => $questionnaire->getSlug()
        ]);
    }

    private function getRecordsFromQuestionnaire($questionnaire){
        $user = $this->getUser();
        $topics = $this->getDoctrine()->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $records = [];

        foreach ($topics as $topic){
            $statements = $this->getDoctrine()->getRepository(Statement::class)->findBy(['topic' => $topic]);
            foreach ($statements as $statement){
                $record = $this->getDoctrine()->getRepository(Record::class)->findByStatementAndUser($statement, $user);
                if($record){
                    array_push($records, $record);
                }
            }
        }

        return $records;
    }
}

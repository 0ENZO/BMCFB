<?php

namespace App\Controller;

use Exception;
use App\Entity\User;
use App\Entity\Topic;
use App\Entity\Track;
use App\Entity\Record;
use App\Entity\Profile;
use App\Entity\Statement;
use App\Service\UserResult;
use App\Entity\Questionnaire;
use App\Repository\RecordRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Length;
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
    public function index(Questionnaire $questionnaire, UserResult $userResultService): Response
    {
        $user = $this->getUser();
        $users = [];
        array_push($users, $user);

        $finished = false;
        $records = $userResultService->getUsersRecordsFromQuestionnaire($questionnaire, $users);

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
    public function play(Topic $topic, Request $request): Response
    {
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();
        $statements = $em->getRepository(Statement::class)->findBy(['topic' => $topic]);  
        $questionnaire = $topic->getQuestionnaire();
        $topics = $em->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $maxTopic = count($topics);
        $currentTopic = array_search($topic, $topics) + 1;


        if ($this->isAnswered($topic)){
            $next = $this->nextTopic($questionnaire);
            return $this->redirectToRoute('questionnaire_play', [
                'id' => $next->getId(),
            ]);
        }
        
        $formBuilder = $this->createFormBuilder($statements);
        foreach($statements as $key => $val){
            $formBuilder->add('record'.$key, IntegerType::class, [
                'label' => false,
                'attr' => [
                    'min' => 0,
                    'max'=> 6,
                    'step' => 2,
                    'placeholder' => "0-6",
                    'class' => 'text-muted inputRecord'
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

                $action = 'Questionnaire '.$questionnaire->getId().' finished';
                $existingTrack = $this->getDoctrine()->getRepository(Track::class)->findExistingTrack($user, $action);
                if (!$existingTrack){
                    $addTrack = $this->addTrack($action, $user);
                }

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
            'form' => $form->createView(),
            'currentTopic' => $currentTopic,
            'maxTopic' => $maxTopic
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

    private function isAnswered($topic){
        $users = [];
        array_push($users, $this->getUser());
        $records = $this->getDoctrine()->getRepository(Record::class)->findByTopicAndUsers($topic, $users);
        if($records){
            return true;
        }else{
            return false; 
        }
    }

    /**
     * Calculate results and display resulsts (canva + text)
     * @Route("/{slug}/bilan", name="questionnaire_bilan")
     */
    public function bilan(Questionnaire $questionnaire, UserResult $userResultService)
    {
        $user = $this->getUser();
        $finished = false;

        try {
            if (is_null($this->nextTopic($questionnaire))){
                $finished = true;
            }
        } catch (Exception $e) {
            return $this->render('questionnaire/index.html.twig', [
                'user' => $user,
                'questionnaire' => $questionnaire,
                'exception' => $e->getMessage()
            ]); 
        }

        if ($finished == false) {
            $this->addFlash('warning', "Vous devez compléter le questionnaire pour accéder à votre bilan");
            return $this->redirectToRoute('questionnaire_show', [
                'slug' => $questionnaire->getSlug(),
            ]);
        }

        $finalResults = $userResultService->getUserResultsFromQuestionnaire($questionnaire, $user);

        $profileNames = $finalResults['profileNames'];
        $profileRates = $finalResults['profileRates'];
        $average = $finalResults['average'];
        $axisNames = $finalResults['axisNames'];
        $axisRates = $finalResults['axisRates'];

        return $this->render('questionnaire/bilan.html.twig', [
            'user' => $user,
            'questionnaire' => $questionnaire, 
            'profileNames' => json_encode($profileNames),
            'profileRates' => json_encode($profileRates),
            'average' => json_encode($average),
            'axisNames' => json_encode($axisNames),
            'axisRates' => json_encode($axisRates),
        ]);
    }

    /**
     * Delete all user records from the questionnaire
     * @Route("/{slug}/reset", name="questionnaire_reset")
     */
    public function reset(Questionnaire $questionnaire, RecordRepository $recordRepository)
    {
        $user = $this->getUser();
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

    /**
     * Fonction qui permet à un admin de remplir le questionnaire aléatoirement
     * @IsGranted("ROLE_ADMIN")
     * @Route("/complete/{slug}", name="questionnaire_fill")
     */
    public function fillRandomly(Questionnaire $questionnaire, UserResult $userResultService){

        $user = $this->getUser();
        $topics = $this->getDoctrine()->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $statements = $this->getDoctrine()->getRepository(Statement::class)->findBy(['topic' => $topics[0]]);  
        $em = $this->getDoctrine()->getManager();
        
        $users = [];
        array_push($users, $user);

        $records = $userResultService->getUsersRecordsFromQuestionnaire($questionnaire, $users);
        if ($records) {
            $this->addFlash('warning', 'Vous devez supprimer vos résultats pour pouvoir générer des réponses aléatoires');

            return $this->redirectToRoute('questionnaire_show', [
                'slug' => $questionnaire->getSlug(),
            ]);
        }

        $rates = [0,2,4,6]; 
        for ($i=0; $i < count($statements)-4 ; $i++) { 
            array_push($rates, $rates[rand(0,count($rates)-1)]);
        }

        foreach ($topics as $topic) {
            $statements = $this->getDoctrine()->getRepository(Statement::class)->findBy(['topic' => $topic]);  
            foreach ($statements as $statement) {
                $record = new Record();
                $record->setUser($user);
                $record->setStatement($statement);
                $record->setRate($rates[rand(0, count($statements)-1)]);
                $record->setDate(new \DateTime());
                $em->persist($record);
            }
        }

        $em->flush();
        $this->addFlash('info', 'Le questionnaire a bien été rempli aléatoirement');

        return $this->redirectToRoute('questionnaire_show', [
            'slug' => $questionnaire->getSlug(),
        ]);
    }

    /**
     * Renvoie les deux profils les plus présents chez l'utilisateur (ex: Réaliste et Formateur)
     * @param [type] $userProfiles
     * @return void
     */
    private function getLargestProfiles(array $userProfiles)
    {
        $finalUserProfiles = [];

        $max = max($userProfiles);
        $key = array_search($max, $userProfiles);
        array_push($finalUserProfiles, $this->getDoctrine()->getRepository(Profile::class)->findOneById($key));
        unset($userProfiles[$key]);

        $max = max($userProfiles);
        $key = array_search($max, $userProfiles);
        array_push($finalUserProfiles, $this->getDoctrine()->getRepository(Profile::class)->findOneById($key));

        return $finalUserProfiles;
    }
    
    private function addTrack($action, $user)
    {
        $track = new Track();
        $track->setDate(new \DateTime());
        $track->setAction($action);
        $track->setUser($user);
        $em = $this->getDoctrine()->getManager();
        $em->persist($track);
        $em->flush();
    }
}

<?php

namespace App\Controller;

use App\Entity\Profile;
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
use Symfony\Component\Validator\Constraints\Length;

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
        $records = $this->getUserRecordsFromQuestionnaire($questionnaire);

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
        $questionnaire = $this->getDoctrine()->getRepository(Questionnaire::class)->findOneBySlug($slug);
        $profiles = $this->getDoctrine()->getRepository(Profile::class)->findByQuestionnaire($questionnaire);
        $records = $this->getUserRecordsFromQuestionnaire($questionnaire);
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
            $this->addFlash('warning', 'Vous ne pouvez pas accéder à votre bilan tant que vous n\'avez pas finit de compléter le questionnaire');
            return $this->redirectToRoute('questionnaire_show', [
                'slug' => $questionnaire->getSlug(),
            ]);
        }

        $userProfiles = [];
        $profileRates = [];
        $profileNames = [];

        foreach($profiles as $profile) {
            $userProfiles[$profile->getId()] = 0;
            array_push($profileNames, $profile->getTitle());
        }   

        foreach ($records as $record) {
            $idProfile = $record[0]->getStatement()->getProfile()->getId();
            $userProfiles[$idProfile] += $record[0]->getRate();
        }

        foreach ($userProfiles as $key => $value) {
            array_push($profileRates, $value);
        }
        
        $average = array_sum($profileRates)/count($profileRates);

        $axisNames = ["sens", "systeme", "social", "coherence"];
        $axisRates = [];
        foreach ($axisNames as $name){
            array_push($axisRates, $this->getAxisAverage($name, $profiles, $userProfiles));
        }

        return $this->render('questionnaire/bilan.html.twig', [
            'user' => $user,
            'questionnaire' => $questionnaire, 
            'profileNames' => json_encode($profileNames),
            'profileRates' => json_encode($profileRates),
            'average' => json_encode($average),
            'axisNames' => json_encode($axisNames),
            'axisRates' => json_encode($axisRates)
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

    /**
     * Pour chaque question lié à un questionnaire, récupère la réponse associée de l'utilisateur 
     * @param [type] $questionnaire
     */
    private function getUserRecordsFromQuestionnaire($questionnaire){
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

    /**
     * Fonction qui permet à un admin de remplir le questionnaire aléatoirement
     * @IsGranted("ROLE_ADMIN")
     * @Route("/complete/{slug}", name="questionnaire_fill")
     */
    public function fillRandomly($slug){

        $user = $this->getUser();
        $questionnaire = $this->getDoctrine()->getRepository(Questionnaire::class)->findOneBySlug($slug);
        $topics = $this->getDoctrine()->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $statements = $this->getDoctrine()->getRepository(Statement::class)->findBy(['topic' => $topics[0]]);  
        $em = $this->getDoctrine()->getManager();

        $records = $this->getUserRecordsFromQuestionnaire($questionnaire);
        if ($records) {
            $this->addFlash('warning', 'Vous devez supprimer vos résultats pour pouvoir générer des réponses aléatoires');

            return $this->redirectToRoute('questionnaire_show', [
                'slug' => $slug,
            ]);
        }

        $rates = ["0","2","4","6"];

        for ($i=0; $i < count($statements)-4 ; $i++) { 
            array_push($rates, rand(0,6));
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
            'slug' => $slug,
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

    /**
     * Undocumented function
     *
     * @param string $axis
     * @param array $profiles
     * @param array $userProfiles
     * @return int
     */
    private function getAxisAverage(string $axis, array $profiles, array $userProfiles)
    {
        $result = 0;

        if (strcmp(trim(strtolower($axis)), 'sens') == 0 ){

            $key = array_search('Entrepreneur', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];
            $key = array_search('Directif', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];

        }elseif (strcmp(trim(strtolower($axis)), 'systeme') == 0) {

            $key = array_search('Réaliste', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];
            $key = array_search('Improvisateur', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];

        }elseif (strcmp(trim(strtolower($axis)), 'social') == 0) {

            $key = array_search('Participatif', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];
            $key = array_search('Arrangeant ou conciliant', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];

        }elseif (strcmp(trim(strtolower($axis)), 'coherence') == 0) {

            $key = array_search('Organisateur', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];
            $key = array_search('Formaliste', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];

        }else{
            return new \Exception('Aucun axe correspondant n\'a été trouvé');
        }

        return round($result/2);
    }
}

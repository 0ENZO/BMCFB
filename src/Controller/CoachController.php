<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Topic;
use App\Entity\Track;
use App\Entity\Record;
use App\Entity\Profile;
use App\Form\TopicType;
use App\Entity\Statement;
use App\Form\ProfileType;
use App\Form\StatementType;
use App\Service\UserResult;
use App\Entity\Questionnaire;
use App\Form\QuestionnaireType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/coach")
 * @IsGranted("ROLE_COACH")
 */
class CoachController extends AbstractController
{
    /**
     * @Route("/", name="coach_index")
     */
    public function index()
    {
        $questionnaires = $this->getDoctrine()->getRepository(Questionnaire::class)->findAll();
        
        return $this->render('coach/index.html.twig', [
            'questionnaires' => $questionnaires
        ]);
    }

    /**
     * @Route("/{id}/edit", name="questionnaire_edit", requirements={"questionnaire"="\d+"})
     */
    public function questionnaire_edit(Questionnaire $questionnaire, Request $request, EntityManagerInterface $em)
    {
        $profiles = $em->getRepository(Profile::class)->findByQuestionnaire($questionnaire);
        $topics = $em->getRepository(Topic::class)->findByQuestionnaire($questionnaire);
        $statements = $em->getRepository(Statement::class)->findQuestionnaireStatements($questionnaire);

        $profile = new Profile();
        $profile->setQuestionnaire($questionnaire);
        $form_profile = $this->createForm(ProfileType::class, $profile);
        $form_profile->remove('questionnaire');

        if ($form_profile->isSubmitted() && $form_profile->isValid()) {
            $em->persist($profile);
            $em->flush();
            $this->addFlash('info', "Profil ajouté");
            return $this->redirectToRoute('questionnaire_edit', [
                'id' => $questionnaire->getId(),
            ]);
        }

        $topic = new Topic();
        $topic->setQuestionnaire($questionnaire);
        $form_topic = $this->createForm(TopicType::class, $topic);
        $form_topic->remove('questionnaire');

        if ($form_topic->isSubmitted() && $form_topic->isValid()) {
            $em->persist($topic);
            $em->flush();
            $this->addFlash('info', "Sujet ajouté");
            return $this->redirectToRoute('questionnaire_edit', [
                'id' => $questionnaire->getId(),
            ]);
        }

        $statement = new Statement();
        $form_statement = $this->createForm(StatementType::class, $statement);

        if ($form_statement->isSubmitted() && $form_statement->isValid()) {
            $em->persist($statement);
            $em->flush();
            $this->addFlash('info', "Affirmation ajoutée");
            return $this->redirectToRoute('questionnaire_edit', [
                'id' => $questionnaire->getId(),
            ]);
        }

        $form = $this->createForm(QuestionnaireType::class, $questionnaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){

            $em->flush();
            $this->addFlash('info', "Questionnaire modifié");
            return $this->redirectToRoute('questionnaire_edit', [
                'id' => $questionnaire->getId(),
            ]);
        }

        $action = 'Questionnaire '.$questionnaire->getId().' finished';
        $finishedTracks = $em->getRepository(Track::class)->findByAction($action);

        return $this->render('coach/questionnaire/edit.html.twig', [
            'questionnaire' => $questionnaire,
            'form' => $form->createView(),
            'finishedTracks'=> $finishedTracks,
            'profiles' => $profiles,
            'topics' => $topics,
            'statements' => $statements,
            'form_profile' => $form_profile->createView(),
            'form_topic' => $form_topic->createView(),
            'form_statement' => $form_statement->createView()
        ]);
    }

    /**
     * @Route("unit/edit/{id}/{type}", name="unit_edit")
     */
    public function unit_edit(EntityManagerInterface $em, Request $request, $id, $type)
    {

        if ($type == 'profil'){
            $profile = $em->getRepository(Profile::class)->findOneById($id);
            $questionnaire = $profile->getQuestionnaire();
            $profiles = $em->getRepository(Profile::class)->findByQuestionnaire($questionnaire);
            $current = array_search($profile, $profiles);
            $max = count($profiles);
            $form = $this->createForm(ProfileType::class, $profile);
            $form->handleRequest($request);
    
            if ($form->isSubmitted() && $form->isValid()){
    
                $em->flush();
                $this->addFlash('info', "Sujet modifié");
                return $this->redirectToRoute('questionnaire_edit', [
                    'id' => $questionnaire->getId(),
                ]);
            }
        }

        if ($type == 'sujet'){
            $topic = $em->getRepository(Topic::class)->findOneById($id);
            $questionnaire = $topic->getQuestionnaire();
            $topics = $em->getRepository(Topic::class)->findByQuestionnaire($questionnaire);
            $current = array_search($topic, $topics);
            $max = count($topics);
            $form = $this->createForm(TopicType::class, $topic);
            $form->handleRequest($request);
    
            if ($form->isSubmitted() && $form->isValid()){
    
                $em->flush();
                $this->addFlash('info', "Sujet modifié");
                return $this->redirectToRoute('questionnaire_edit', [
                    'id' => $questionnaire->getId(),
                ]);
            }
        }

        if ($type == 'affirmation'){
            $statement = $em->getRepository(Statement::class)->findOneById($id);
            $questionnaire = $statement->getTopic()->getQuestionnaire();
            $statements = $em->getRepository(Statement::class)->findQuestionnaireStatements($questionnaire);
            $current = array_search($statement, $statements);
            $max = count($statements);

            $form = $this->createForm(StatementType::class, $statement);
            $form->handleRequest($request);
    
            if ($form->isSubmitted() && $form->isValid()){
    
                $em->flush();
                $this->addFlash('info', "Affirmation modifiée");
                return $this->redirectToRoute('questionnaire_edit', [
                    'id' => $questionnaire->getId(),
                ]);
            }
        }
        return $this->render('coach/questionnaire/unit_edit.html.twig', [
            'form' => $form->createView(),
            'questionnaire' => $questionnaire,
            'type' => $type,
            'current' => $current+1,
            'max' => $max+1,
        ]);
    }

    /**
    * @Route("unit/delete/{id}/{$type}", name="unit_delete", methods={"DELETE", "GET"})
    */
    public function unit_delete(EntityManagerInterface $em, Request $request, $id, $type): Response
    {

        if ($type == 'profil'){
            $entity = $em->getRepository(Profile::class)->findOneById($id);
            $questionnaire = $entity->getQuestionnaire();
        }

        if ($type == 'sujet'){
            $entity = $em->getRepository(Topic::class)->findOneById($id);
            $questionnaire = $entity->getQuestionnaire();
        }

        if ($type == 'affirmation'){
            $entity = $em->getRepository(Statement::class)->findOneById($id);
            $questionnaire = $entity->getTopic()->getQuestionnaire();
        }

        $em->remove($entity);
        $em->flush();

        $this->addFlash('info', " supprimé");
        return $this->redirectToRoute('questionnaire_edit', [
            'id' => $questionnaire->getId(),
        ]);
    }

    /**
     * @Route("/{id}/results", name="coach_results", requirements={"questionnaire"="\d+"})
     */
    public function results(Questionnaire $questionnaire, UserResult $userResultService)
    {
        $users = $this->getQuestionnaireGoodStudents($questionnaire);

        if ($users) {

            $profiles = $this->getDoctrine()->getRepository(Profile::class)->findBy([
                'questionnaire' => $questionnaire,
            ]);
            $rates = $this->getQuestionnaireProfilesResults($questionnaire);
            
            $names = [];
            foreach($profiles as $profile) {
                $names[] = $profile->getTitle();
            }

            $axisNames = ["sens", "systeme", "social", "coherence"];
            foreach ($axisNames as $axis) {
                array_push($names, $axis);
                array_push($rates, $this->getAxisAverage($axis, $names, $rates));
            }

            array_push($names, "Leadership");
            array_push($rates, $userResultService->getLeadershipIndex($this->getQuestionnairesAverageResults($questionnaire)));

            array_push($names, "Management");
            array_push($rates, $userResultService->getManagementIndex($this->getQuestionnairesAverageResults($questionnaire)));

            array_push($names, "Fiabilité");
            array_push($rates, $userResultService->getFiabilityIndex($rates));



            $usersScores = [];
            foreach ($users as $user) {
                $usersScores[] = $this->getScores($user, $questionnaire);
            }

            $averageScores = $this->getAverage($usersScores);

            return $this->render('coach/questionnaire/results.html.twig', [
                'questionnaire' => $questionnaire,
                'users' => $users,
                'rates' => $averageScores,
                'names' => $names
            ]);

        } else {

            $this->addFlash('warning', 'Aucun participant n\'a encore répondu au questionnaire, impossible de visualiser les résultats.');
            return $this->redirectToRoute('coach_index');
        }
    }
    
    /**
     * @Route("/{id}/{email}/results", name="user_results")
     */
    public function user_result(Questionnaire $questionnaire, $email, UserResult $userResultService)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->findOneByEmail($email);
        $finalResults = $userResultService->getUserResultsFromQuestionnaire($questionnaire, $user);

        $profileNames = $finalResults['profileNames'];
        $profileRates = $finalResults['profileRates'];
        $axisNames = $finalResults['axisNames'];
        $axisRates = $finalResults['axisRates'];

        $indexNames = ["Leadership", "Management", "Fiabilité"];
        $indexRates = [];

        $users = [];
        array_push($users, $user);

        array_push($indexRates, $userResultService->getLeadershipIndex($userResultService->getUserRecordsRatesFromQuestionnaire($questionnaire, $users)));
        array_push($indexRates, $userResultService->getManagementIndex($userResultService->getUserRecordsRatesFromQuestionnaire($questionnaire, $users)));
        array_push($indexRates, $userResultService->getFiabilityIndex($profileRates));

        return $this->render('coach/user/results.html.twig', [
            'user' => $user,
            'questionnaire' => $questionnaire, 
            'profileNames' => $profileNames,
            'profileRates' => $profileRates,
            'axisNames' => $axisNames,
            'axisRates' => $axisRates,
            'indexNames' => $indexNames,
            'indexRates' => $indexRates
        ]);
    }


    

    /**
     * Retourne la liste des users ayant terminé le questionnaire
     * @param [type] $questionnaire
     * @return User[]
     */
    private function getQuestionnaireGoodStudents($questionnaire)
    {
        $topics = $this->getDoctrine()->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);   

        if($topics) {
            $statements = $this->getDoctrine()->getRepository(Statement::class)->findBy(['topic' => $topics[count($topics) - 1]]);        
            $users = $this->getDoctrine()->getRepository(User::class)->findAsAnswered($statements[count($statements) - 1]);
            return $users;
        }
        return null;
    }

    /**
     * Retourne un tableau qui comprend la moyenne des résultats de tous les utilisateurs pour chaque profil
     *
     * @param [type] $questionnaire
     * @return 
     */
    private function getQuestionnaireProfilesResults($questionnaire, $users = null) {

        if ($users == null){
            $users = $this->getQuestionnaireGoodStudents($questionnaire);
        }
        $profiles = $this->getDoctrine()->getRepository(Profile::class)->findByQuestionnaire($questionnaire);
        $topics = $this->getDoctrine()->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $records = $this->getDoctrine()->getRepository(Record::class)->findByTopicsAndUsers($topics, $users);

        $rates = [];
        for ($i=0; $i < count($profiles); $i++) { 
            array_push($rates, 0);
        }

        for ($countRecord = 0; $countRecord < count($records);) { 
            for ($countProfiles = 0; $countProfiles < count($profiles); $countProfiles++) { 
                for ($countUsers = 0; $countUsers < count($users); $countUsers++) { 
                    /* Fix pour afficher les résultats en local, countRecord accède à l'emplacement 
                       count($records) alors qu'il ne peut pas aller au delà de count($records)-1 */
                    if($countRecord < count($records)){ 
                        $rates[$countProfiles] += $records[$countRecord]->getRate();
                        $countRecord++;
                    }
                }
                $countUsers = 0;
            }
        } 

        for ($i=0; $i < count($rates); $i++) { 
            $rates[$i] = intval(round($rates[$i] / count($users)));
        }

        return $rates;
    }

    /**
     *
     * @param string $axis
     * @param array $names
     * @param array $userProfiles
     * @return int
     */
    private function getAxisAverage(string $axis, array $names, array $rates)
    {
        $result = 0;

        if (strcmp(trim(strtolower($axis)), 'sens') == 0 ){

            $key = array_search('Entrepreneur', $names);
            $result += $rates[$key];
            $key = array_search('Directif', $names);
            $result += $rates[$key];

        }elseif (strcmp(trim(strtolower($axis)), 'systeme') == 0) {

            $key = array_search('Réaliste', $names);
            $result += $rates[$key];
            $key = array_search('Improvisateur', $names);
            $result += $rates[$key];

        }elseif (strcmp(trim(strtolower($axis)), 'social') == 0) {

            $key = array_search('Participatif', $names);
            $result += $rates[$key];
            $key = array_search('Arrangeant ou conciliant', $names);
            $result += $rates[$key];

        }elseif (strcmp(trim(strtolower($axis)), 'coherence') == 0) {

            $key = array_search('Organisateur', $names);
            $result += $rates[$key];
            $key = array_search('Formaliste', $names);
            $result += $rates[$key];

        }else{
            return new \Exception('Aucun axe correspondant n\'a été trouvé');
        }

        return intval(round($result/2));
    }

    /**
     * Retourne la moyenne générale des records pour chaque statement
     *
     * @param [type] $questionnaire
     */
    private function getQuestionnairesAverageResults($questionnaire)
    {
        $users = $this->getQuestionnaireGoodStudents($questionnaire);
        $topics = $this->getDoctrine()->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $records = $this->getDoctrine()->getRepository(Record::class)->findByTopicsAndUsers($topics, $users);
        $size = count($topics) * count($topics[0]->getStatements());

        $condensedRecords = [];
        for ($i=0; $i < $size; $i++) { 
            array_push($condensedRecords, 0);
        }

        for ($countRecord = 0; $countRecord < count($records);) { 
            for ($countSize = 0; $countSize < $size; $countSize++) { 
                for ($countUsers = 0; $countUsers < count($users); $countUsers++) { 
                    $condensedRecords[$countSize] += $records[$countRecord]->getRate();
                    $countRecord++;
                }
                $countUsers = 0;
            }
        } 

        for ($i=0; $i < $size; $i++) { 
            $condensedRecords[$i] = intval(round($condensedRecords[$i] / count($users)));
        }

        return $condensedRecords;
    }

    /**
     * Retourne la moyenne d'une liste de records
     *
     * @param [type] $questionnaire
     * @param array $index
     * @return int
     */
    private function getAverageRecords($questionnaire, array $index, string $type = null)
    {
        $records = $this->getQuestionnairesAverageResults($questionnaire);
        $result = 0;

        for ($i=0; $i < count($index); $i++) { 
            $result += $records[$index[$i]];
        }

        if($type){
            if (strcmp(trim(strtolower($type)), 'leadership') == 0 ){
                $result = intval(round($result / 3.6));
            } elseif (strcmp(trim(strtolower($type)), 'management') == 0 ) {
                $result = intval(round($result / 7.2));
            }
        }

        return $result;
    }

    /**
     * Retourne les 13 scores
     * @param $user
     * @param $questionnaire
     * @return array $data[]
     */
    private function getScores($user, $questionnaire) {

        $data = [];

        $profiles = $this->getDoctrine()->getRepository(Profile::class)->findBy([
            'questionnaire' => $questionnaire
        ]);

        $records = $this->getDoctrine()->getRepository(Record::class)->findBy([
            'user' => $user
        ]);

        // crée les clés du tableau dans l'ordre
        foreach ($profiles as $profile) {
            $data[$profile->getTitle()] = 0;
        }
        $data['Sens'] = 0;
        $data['Système'] = 0;
        $data['Social'] = 0;
        $data['Cohérence'] = 0;
        $data['Leadership'] = 0;
        $data['Management'] = 0;

        // ajoute les valeurs du tableau
        $indexLeadership = [3, 9, 17, 25, 36, 43];
        $indexManagement = [1, 2, 10, 12, 18, 19, 26, 27, 33, 34, 43, 44];
        foreach ($records as $key => $record) {

            $profile = $record->getStatement()->getProfile();
            $data[$profile->getTitle()] += $record->getRate();

            if(in_array($key + 1, $indexLeadership)) {
                $data['Leadership'] += $record->getRate();
            }

            if (in_array($key + 1, $indexManagement)) {
                $data['Management'] += $record->getRate();
            }
            
        }

        // ajoute les informations calculées
        $data['Sens'] = ($data['Entrepreneur'] + $data['Directif']) / 2;
        $data['Système'] = ($data['Réaliste'] + $data['Improvisateur']) / 2;
        $data['Social'] = ($data['Participatif'] + $data['Arrangeant ou conciliant']) / 2;
        $data['Cohérence'] = ($data['Organisateur'] + $data['Formaliste']) / 2;

        $data['Leadership'] = $data['Leadership'] / 3.6;
        $data['Management'] = $data['Management'] / 7.2;

        $data['Fiabilité'] =
            abs($data['Entrepreneur'] - $data['Directif']) + 
            abs($data['Réaliste'] - $data['Improvisateur']) + 
            abs($data['Participatif'] - $data['Arrangeant ou conciliant']) + 
            abs($data['Organisateur'] - $data['Formaliste'])
        ;

        return $data;

    }

    /**
     * Prend des jeux de 13 scores
     * Retourne les 13 scores moyens
     * @param array $usersScores[]
     * @return array $averageScores[]
     */
    private function getAverage($usersScores) {

        // Définit les clés
        $keys = array_keys($usersScores[0]);

        // Remplit les valeurs
        foreach ($keys as $key) {
            $averageScores[$key] = 0;
            $values = [];
            foreach ($usersScores as $userScores) {
                $values[] = $userScores[$key];
            }
            $averageScores[$key] = array_sum($values) / count($values);
        }

        return $averageScores;

    }

    /**
     * @Route("/calculate_results/{id}", name="calculate_results", methods={"GET","POST"}, requirements={"id"="\d+"})
     * @IsGranted("ROLE_COACH")
     * Appel Ajax
     */
    public function calculateResults(Questionnaire $questionnaire, UserResult $userResultService, Request $request): Response
    {

        if ($request->isXMLHttpRequest()) {
            
            // Tableau des users
            $usersList = json_decode($request->getContent());
            $users = [];
            foreach ($usersList as $uid) {
                $users[] = $this->getDoctrine()->getRepository(User::class)->find($uid);
            }

            // Tableau des scores
            $usersScores = [];
            foreach ($users as $user) {
                $usersScores[] = $this->getScores($user, $questionnaire);
            }

            // Tableau des scores moyens
            $averageScores = $this->getAverage($usersScores);

            // Séparation du tableau pour correspondre aux données cibles
            $rates = [];
            $names = [];
            foreach ($averageScores as $key => $value) {
                $rates[] = $value;
                $names[] = $key;
            }

            return new JsonResponse([
                'rates' => $rates,
                'names' => $names
            ]);
            
        } else {
            return new Response('This is not ajax!', 400);
        }
        
    }

}
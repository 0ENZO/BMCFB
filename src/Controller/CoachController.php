<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Topic;
use App\Entity\Record;
use App\Entity\Profile;
use App\Entity\Statement;
use App\Entity\Questionnaire;
use App\Service\UserResult;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
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
     * @Route("/{id}/results", name="coach_results", requirements={"questionnaire"="\d+"})
     */
    public function results(Questionnaire $questionnaire, UserResult $userResultService)
    {
        $users = $this->getQuestionnaireGoodStudents($questionnaire);

        if($users){
            $profiles = $this->getDoctrine()->getRepository(Profile::class)->findByQuestionnaire($questionnaire);
            $rates = $this->getQuestionnaireProfilesResults($questionnaire);
            $names = [];

            foreach($profiles as $profile) {
                array_push($names, $profile->getTitle());
            }   

            $axisNames = ["sens", "systeme", "social", "coherence"];
            foreach ($axisNames as $axis){
                array_push($names, $axis);
                array_push($rates, $this->getAxisAverage($axis, $names, $rates));
            }

            array_push($names, "Leadership");
            array_push($rates, $userResultService->getLeadershipIndex($this->getQuestionnairesAverageResults($questionnaire)));

            array_push($names, "Management");
            array_push($rates, $userResultService->getManagementIndex($this->getQuestionnairesAverageResults($questionnaire)));

            array_push($names, "Fiabilité");
            array_push($rates, $userResultService->getFiabilityIndex($rates));

            return $this->render('coach/questionnaire/results.html.twig', [
                'questionnaire' => $questionnaire,
                'users' => $users,
                'rates' => $rates,
                'names' => $names
            ]);
        }else{
            $this->addFlash('warning', 'Le questionnaire est en cours de rédaction, impossible de visualiser les résultats.');
            return $this->redirectToRoute('coach_index');
        }
    }
    
    /**
     * @Route("/new/{id}/{header}", name="module_new", methods={"GET","POST"})
     * @IsGranted("ROLE_USER")
    */
    //public function new(Request $request, Course $course, ModuleRepository $moduleRepository, $header = 0): Response

    /**
     * @Route("/{id}/{email}/results", name="user_results")
     */
    public function user_result(Questionnaire $questionnaire, $email, UserResult $userResultService)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->findOneByEmail($email);
        $finalResults = $userResultService->getUserResultsFromQuestionnaire($questionnaire, $user);

        $profileNames = $finalResults[0];
        $profileRates = $finalResults[1];
        $axisNames = $finalResults[3];
        $axisRates = $finalResults[4];
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

        if($topics){
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
    private function getQuestionnaireProfilesResults($questionnaire){

        $users = $this->getQuestionnaireGoodStudents($questionnaire);
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
                    $rates[$countProfiles] += $records[$countRecord]->getRate();
                    $countRecord++;
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
}

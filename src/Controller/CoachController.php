<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Topic;
use App\Entity\Record;
use App\Entity\Profile;
use App\Entity\Statement;
use App\Entity\Questionnaire;
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
    public function results(Questionnaire $questionnaire)
    {
        $users = $this->getQuestionnaireGoodStudents($questionnaire);

        if($users){

            $profiles = $this->getDoctrine()->getRepository(Profile::class)->findByQuestionnaire($questionnaire);
            $rates = $this->getQuestionnaireResults($questionnaire);
            $names = [];

            foreach($profiles as $profile) {
                array_push($names, $profile->getTitle());
            }   

            $this->getQuestionnairesRecords($questionnaire);

            $index = ["3","9","17","25","36","43"];
            $type = "leadership";
            array_push($names, $type);
            array_push($rates, $this->getAverageRecords($questionnaire, $index, $type));

            $index = ["1", "2", "10", "12", "18", "19", "26", "27", "33", "34", "43", "44"];
            $type = "management";
            array_push($names, $type);
            array_push($rates, $this->getAverageRecords($questionnaire, $index, $type));

            $type = "fiabilité";
            array_push($names, $type);
            array_push($rates, $this->getFiabilityIndex($questionnaire));

            $this->getFiabilityIndex($questionnaire);

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
    private function getQuestionnaireResults($questionnaire){

        $users = $this->getQuestionnaireGoodStudents($questionnaire);
        $topics = $this->getDoctrine()->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $profiles = $this->getDoctrine()->getRepository(Profile::class)->findByQuestionnaire($questionnaire);
        $records = $this->getDoctrine()->getRepository(Record::class)->findByTopicsAndUsers($topics, $users);
        
        // initialise un tableau de notes de taille(nb de profiles) à 0
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
            $rates[$i] = round($rates[$i] / count($users));
        }

        return $rates;
    }

    /**
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

    /**
     * Retourne la moyenne de chaque record 
     *
     * @param [type] $questionnaire
     */
    private function getQuestionnairesRecords($questionnaire)
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
            $condensedRecords[$i] = round($condensedRecords[$i] / count($users));
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
        $records = $this->getQuestionnairesRecords($questionnaire);
        $result = 0;

        for ($i=0; $i < count($index); $i++) { 
            $result += $records[$index[$i]];
        }

        if($type){
            if (strcmp(trim(strtolower($type)), 'leadership') == 0 ){
                $result = round($result / 3.6);
            } elseif (strcmp(trim(strtolower($type)), 'management') == 0 ) {
                $result = round($result / 7.2);
            }
        }

        return $result;
    }


    /**
     * Retourne l'indice de fiabilité des résultats du questionnaire,
     * soit la somme des écarts en valeur absolue entre les résultats d'un profil et son suivant
     * @param [type] $questionnaire
     * @return int
     */
    private function getFiabilityIndex($questionnaire)
    {
        $records = $this->getQuestionnaireResults($questionnaire);
        $abs = [];

        if (count($records) % 2 == 0) {

            for ($i=0; $i < count($records) / 2; $i++) { 
                array_push($abs, 0);
            }

            for ($i=0; $i < count($records); $i+=2) { 
                $abs[$i/2] = abs($records[$i] - $records[$i + 1]);
            }
        }
        
        return array_sum($abs);
    }

    /**
     *
     * @param [type] $questionnaire
     * @return Record[] 
     */
    private function getAllQuestionnaireRecords($questionnaire){
        $topics = $this->getDoctrine()->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $records = [];

        foreach ($topics as $topic){
            $statements = $this->getDoctrine()->getRepository(Statement::class)->findBy(['topic' => $topic]);
            foreach ($statements as $statement){
                $record = $this->getDoctrine()->getRepository(Record::class)->findByStatement($statement);
                if($record){
                    array_push($records, $record);
                }
            }
        }
        return $records;
    }
}

<?php
namespace App\Service;

use App\Entity\Topic;
use App\Entity\Record;
use App\Entity\Profile;
use App\Entity\Statement;
use Doctrine\ORM\EntityManagerInterface;

class UserResult
{

    private $em;

    public function __construct(EntityManagerInterface $em) {
        $this->em = $em;
    }
       
    /**
     * Pour chaque question lié à un questionnaire, récupère la réponse associée de l'utilisateur 
     * @param [type] $questionnaire
     * @return mixed
     */
    /*
    public function getUsersRecordsFromQuestionnaire($questionnaire, $user)
    {
        $topics = $this->em->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $records = [];

        foreach ($topics as $topic){
            $statements = $this->em->getRepository(Statement::class)->findBy(['topic' => $topic]);
            foreach ($statements as $statement){
                $record = $this->em->getRepository(Record::class)->findByStatementAndUser($statement, $user);
                if($record){
                    array_push($records, $record);
                }
            }
        }

        return $records;
    }
    */
    
    /**
     * Pour un questionnaire donné, retourne tous les records des utilisateurs présent dans la liste $users
     * 
     * Renvoie un tableau d'objets si 1 user et un tableau d'entier si plusieurs users
     * @param [type] $questionnaire
     * @param [type] $users
     * @return array
     */
    public function getUsersRecordsFromQuestionnaire($questionnaire, $users)
    {
        $topics = $this->em->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $records = $this->em->getRepository(Record::class)->findByTopicsAndUsers($topics, $users);

        if (count($users) > 1) {      
            $size = count($topics) * count($topics[0]->getStatements());

            $condensedRecords = [];
            for ($i=0; $i < $size; $i++) { 
                array_push($condensedRecords, 0);
            }

            for ($countRecord = 0; $countRecord < count($records);) { 
                for ($countSize = 0; $countSize < $size; $countSize++) { 
                    for ($countUsers = 0; $countUsers < count($users); $countUsers++) { 
                        $condensedRecords[$countSize] += $records[$countRecord];
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
        return $records;
    }

    /**
     * Pour un questionnaire donné, retourne tous les records des utilisateurs présent dans la liste $users
     * @param [type] $questionnaire
     * @param [type] $users
     * @return array
     */
    public function getUserRecordsRatesFromQuestionnaire($questionnaire, $users)
    {
        $topics = $this->em->getRepository(Topic::class)->findBy(['questionnaire' => $questionnaire]);
        $records = $this->em->getRepository(Record::class)->findByTopicsAndUsers($topics, $users);

        $rateRecords = [];
        foreach ($records as $record) {
            array_push($rateRecords, $record->getRate());
        }

        return $rateRecords;
    }

    /**
     * Calcule la note d'un des différents axes définis
     *
     * @param string $axis
     * @param array $profiles
     * @param array $userProfiles
     * @return int
     */
    public function getAxisAverage(string $axis, array $profiles, array $userProfiles)
    {
        $result = 0;

        if (strcmp(trim(strtolower($axis)), 'sens') == 0 ) {

            $key = array_search('Entrepreneur', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];
            $key = array_search('Directif', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];

        } elseif (strcmp(trim(strtolower($axis)), 'systeme') == 0) {

            $key = array_search('Réaliste', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];
            $key = array_search('Improvisateur', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];

        } elseif (strcmp(trim(strtolower($axis)), 'social') == 0) {

            $key = array_search('Participatif', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];
            $key = array_search('Arrangeant ou conciliant', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];

        } elseif (strcmp(trim(strtolower($axis)), 'coherence') == 0) {

            $key = array_search('Organisateur', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];
            $key = array_search('Formaliste', $profiles);
            $result += $userProfiles[$profiles[$key]->getId()];

        } else {
            return new \Exception('Aucun axe correspondant n\'a été trouvé');
        }

        return intval(round($result / 2));
    }

    /**
     * Retourne les notes de profils, la moyenne des notes de profils et les notes d'axes de l'utilisateur
     *
     * @param [type] $questionnaire
     * @param [type] $user
     * @return array $data[]
     */
    public function getUserResultsFromQuestionnaire($questionnaire, $user)
    {

        $data = [];

        $users = [];
        $users[] = $user;

        $records = $this->getUsersRecordsFromQuestionnaire($questionnaire, $users);
        
        $profileNames = [];         // Profils BMCFB : Entrepreneur, réaliste, etc.
        $userProfiles = [];         // Scores par profil

        $profiles = $this->em->getRepository(Profile::class)->findBy([
            'questionnaire' => $questionnaire
        ]);

        foreach ($profiles as $profile) {               // Set les noms de profils et met les scores à zéro
            $userProfiles[$profile->getId()] = 0;
            array_push($profileNames, $profile->getTitle());
        }
        
        foreach ($records as $record) {                 // Ajoute les scores dans les bons profils
            $idProfile = $record->getStatement()->getProfile()->getId();
            $userProfiles[$idProfile] += $record->getRate();
        }

        $userScores = [];                               // Sert uniquement à remettre les clés à zéro ?!
        foreach ($userProfiles as $value) {
            $userScores[] = $value;
        }

        $average = array_sum($userProfiles) / count($userProfiles);

        $data['profileNames'] = $profileNames;
        $data['profileRates'] = $userScores;            // Voir plus haut (!)
        $data['average'] = $average;

        $axisNames = [ "sens", "systeme", "social", "coherence" ];
        $axisRates = [];
        foreach ($axisNames as $name) {
            array_push($axisRates, $this->getAxisAverage($name, $profiles, $userProfiles));
        }
        
        $data['axisNames'] = $axisNames;
        $data['axisRates'] = $axisRates;

        return $data;
        
    }

    /**
     * Prend en paramètre l'intégralité des réponses du répondant et retourne l'indice de leadership
     * @return int
     */
    public function getLeadershipIndex($records) {

        $index = [3, 9, 17, 25, 36, 43];
        
        $result = 0;
        for ($i=0; $i < count($index); $i++) {
            $result += $records[$index[$i] - 1];    // on soustrait car notre tableau commence à l'indice 0
        }

        return round(($result / 3.6), 0);
        
    }

    /**
     * Prend en paramètre l'intégralité des réponses du répondant et retourne l'indice de managament
     * @return int
     */
    public function getManagementIndex($records){
        $index = ["1", "2", "10", "12", "18", "19", "26", "27", "33", "34", "43", "44"];
        $result = 0;

        for ($i=0; $i < count($index); $i++) { 
            $result += $records[$index[$i] - 1];
        }

        return round(($result / 7.2), 0);
    }

    /**
     * Prend en paramètre le résultat de chaque profil et retourne l'indice de fiabiltié
     *  
        * (0,4) ENTREPRENANT - DIRECTIF	
        * (1,6) REALISTE - IMPROVISATEUR	
        * (2,5) PARTICIPATIF - ARRANGEANT	
        * (3,7)  ORGANISATEUR - FORMALISTE
     * @return int
     */
    public function getFiabilityIndex($profileRates) {

        // TODO: erreur de calcul... ça fait les écarts sur les moyennes, donc nécessairement faibles

        $abs = [];

        if (count($profileRates) % 2 == 0) {
            for ($i=0; $i < count($profileRates) / 2; $i++) { 
                // $abs[] = 0;
            }

            $abs[0] = abs($profileRates[0] - $profileRates[4]);     // Entreprenant & Directif
            $abs[1] = abs($profileRates[1] - $profileRates[6]);     // Réaliste & Improvisateur
            $abs[2] = abs($profileRates[2] - $profileRates[5]);     // Participatif & Arrangeant
            $abs[3] = abs($profileRates[3] - $profileRates[7]);     // Organisateur & Formaliste
        }
        
        return array_sum($abs);

    }
}
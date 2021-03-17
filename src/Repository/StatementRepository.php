<?php

namespace App\Repository;

use App\Entity\Statement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Statement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Statement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Statement[]    findAll()
 * @method Statement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Statement::class);
    }

    /**
    * @return Statement[] 
    */
    public function findQuestionnaireStatements($questionnaire)
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.topic', 't')
            ->andWhere('t.questionnaire = :questionnaire')->setParameter('questionnaire', $questionnaire)
            ->getQuery()
            ->getResult()
        ;
    }
}

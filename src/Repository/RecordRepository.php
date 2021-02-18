<?php

namespace App\Repository;

use App\Entity\Record;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Record|null find($id, $lockMode = null, $lockVersion = null)
 * @method Record|null findOneBy(array $criteria, array $orderBy = null)
 * @method Record[]    findAll()
 * @method Record[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Record::class);
    }

    /**
     * @return Record[] Returns an array of Record objects
     */
    public function findByStatementAndUser($statement, $user)
    {
        return $this->createQueryBuilder('r')
            ->where('r.statement = :statement')->setParameter('statement', $statement)
            ->andWhere('r.user = :user')->setParameter('user', $user)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Record[] Returns an array of Record objects
     */
    public function findByTopicAndUsers($topic, $users)
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.statement', 's')
            ->andWhere('s.topic = :topic')->setParameter('topic', $topic)
            ->andWhere('r.user IN (:users)')->setParameter('users', $users)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Record[] Returns an array of Record objects
     */
    public function findByTopicsAndUsers($topics, $users)
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.statement', 's')
            ->andWhere('s.topic IN (:topics)')->setParameter('topics', $topics)
            ->andWhere('r.user IN (:users)')->setParameter('users', $users)
            ->getQuery()
            ->getResult()
        ;
    }
}



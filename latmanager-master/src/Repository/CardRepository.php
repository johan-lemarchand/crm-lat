<?php

namespace App\Repository;

use App\Entity\Card;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }

    public function save(Card $card): void
    {
        $this->getEntityManager()->persist($card);
        $this->getEntityManager()->flush();
    }

    public function remove(Card $card): void
    {
        $this->getEntityManager()->remove($card);
        $this->getEntityManager()->flush();
    }

    public function findByColumnOrderedByPosition(int $columnId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.column = :columnId')
            ->setParameter('columnId', $columnId)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
} 
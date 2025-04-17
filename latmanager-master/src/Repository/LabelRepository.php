<?php

namespace App\Repository;

use App\Entity\Label;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LabelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Label::class);
    }

    public function save(Label $label): void
    {
        $this->getEntityManager()->persist($label);
        $this->getEntityManager()->flush();
    }

    public function remove(Label $label): void
    {
        $this->getEntityManager()->remove($label);
        $this->getEntityManager()->flush();
    }

    public function findByCard(int $cardId): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.cards', 'c')
            ->andWhere('c.id = :cardId')
            ->setParameter('cardId', $cardId)
            ->getQuery()
            ->getResult();
    }
} 
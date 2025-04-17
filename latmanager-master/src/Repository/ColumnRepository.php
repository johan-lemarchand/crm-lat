<?php

namespace App\Repository;

use App\Entity\Column;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ColumnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Column::class);
    }

    public function save(Column $column): void
    {
        $this->getEntityManager()->persist($column);
        $this->getEntityManager()->flush();
    }

    public function remove(Column $column): void
    {
        $this->getEntityManager()->remove($column);
        $this->getEntityManager()->flush();
    }

    public function findByBoardOrderedByPosition(int $boardId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.board = :boardId')
            ->setParameter('boardId', $boardId)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
} 
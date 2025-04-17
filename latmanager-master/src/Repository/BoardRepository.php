<?php

namespace App\Repository;

use App\Entity\Board;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BoardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Board::class);
    }

    public function save(Board $board): void
    {
        $this->getEntityManager()->persist($board);
        $this->getEntityManager()->flush();
    }

    public function remove(Board $board): void
    {
        $this->getEntityManager()->remove($board);
        $this->getEntityManager()->flush();
    }
} 
<?php

namespace App\Command\Infrastructure\Repository;

use App\Command\Domain\Repository\ApiLogRepositoryInterface;
use App\Entity\Command;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineApiLogRepository implements ApiLogRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function deleteApiLogs(Command $command, ?\DateTime $startDate = null, ?\DateTime $endDate = null): void
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->delete('App\Entity\PraxedoApiLog', 'l')
            ->join('l.execution', 'e')
            ->where('e.command = :command')
            ->setParameter('command', $command);

        if ($startDate) {
            $qb->andWhere('e.startedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('e.startedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $qb->getQuery()->execute();
    }
} 
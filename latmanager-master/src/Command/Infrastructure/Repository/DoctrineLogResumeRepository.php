<?php

namespace App\Command\Infrastructure\Repository;

use App\Command\Domain\Repository\LogResumeRepositoryInterface;
use App\Entity\Command;
use App\Entity\LogResume;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoctrineLogResumeRepository extends ServiceEntityRepository implements LogResumeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogResume::class);
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?LogResume
    {
        return parent::findOneBy($criteria, $orderBy);
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    public function deleteLogResumes(
        Command $command,
        ?\DateTime $startDate = null,
        ?\DateTime $endDate = null,
        ?\DateTime $lastExecutionDate = null
    ): void {
        $qb = $this->createQueryBuilder('lr')
            ->delete()
            ->where('lr.command = :command')
            ->setParameter('command', $command);

        if ($lastExecutionDate) {
            $qb->andWhere('lr.executionDate != :lastExecutionDate')
                ->setParameter('lastExecutionDate', $lastExecutionDate);
        }

        if ($startDate && $endDate) {
            $qb->andWhere('lr.executionDate BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
        }

        $qb->getQuery()->execute();
    }
} 
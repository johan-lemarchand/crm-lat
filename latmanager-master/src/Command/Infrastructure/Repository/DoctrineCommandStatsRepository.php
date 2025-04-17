<?php

namespace App\Command\Infrastructure\Repository;

use App\Command\Domain\Repository\CommandStatsRepositoryInterface;
use App\Entity\Command;
use App\Service\SizeCalculator;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineCommandStatsRepository implements CommandStatsRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SizeCalculator $sizeCalculator
    ) {}

    public function getExecutionSize(Command $command): int
    {
        return $this->sizeCalculator->getExecutionSize($command->getId());
    }

    public function getApiLogsSize(Command $command): int
    {
        return $this->sizeCalculator->getApiLogsSize($command->getId());
    }

    public function getResumeSize(Command $command): int
    {
        return $this->sizeCalculator->getResumeSize($command->getId());
    }

    public function getTotalLogs(Command $command): int
    {
        return $this->entityManager->createQuery('
            SELECT COUNT(e.id) 
            FROM App\Entity\CommandExecution e 
            WHERE e.command = :command
        ')
        ->setParameter('command', $command)
        ->getSingleScalarResult();
    }

    public function getExecutionPeriod(Command $command): array
    {
        $firstDate = $this->entityManager->createQuery('
            SELECT MIN(e.startedAt) 
            FROM App\Entity\CommandExecution e 
            WHERE e.command = :command
        ')
        ->setParameter('command', $command)
        ->getSingleScalarResult();

        $lastDate = $this->entityManager->createQuery('
            SELECT MAX(e.startedAt) 
            FROM App\Entity\CommandExecution e 
            WHERE e.command = :command
        ')
        ->setParameter('command', $command)
        ->getSingleScalarResult();

        return [
            'firstDate' => $firstDate ? new \DateTime($firstDate) : null,
            'lastDate' => $lastDate ? new \DateTime($lastDate) : null
        ];
    }
} 
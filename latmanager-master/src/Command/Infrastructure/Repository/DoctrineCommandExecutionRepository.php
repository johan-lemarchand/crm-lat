<?php

namespace App\Command\Infrastructure\Repository;

use App\Command\Domain\Repository\CommandExecutionRepositoryInterface;
use App\Entity\Command;
use App\Entity\CommandExecution;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineCommandExecutionRepository implements CommandExecutionRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->repository = $entityManager->getRepository(CommandExecution::class);
    }

    public function find(int $id): ?CommandExecution
    {
        return $this->repository->find($id);
    }

    public function findByCommand(int $commandId): array
    {
        return $this->repository->findBy(['command' => $commandId], ['startedAt' => 'DESC']);
    }

    public function save(CommandExecution $execution): void
    {
        $this->entityManager->persist($execution);
        $this->entityManager->flush();
    }

    public function findOneBy(array $criteria, array $orderBy = null): ?CommandExecution
    {
        return $this->repository->findOneBy($criteria, $orderBy);
    }

    public function deleteExecutionLogs(
        Command $command,
        ?\DateTime $startDate = null,
        ?\DateTime $endDate = null,
        ?int $lastExecutionId = null
    ): void {
        $qb = $this->entityManager->createQueryBuilder()
            ->delete('App\Entity\CommandExecution', 'e')
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

        if ($lastExecutionId) {
            $qb->andWhere('e.id != :lastExecutionId')
                ->setParameter('lastExecutionId', $lastExecutionId);
        }

        $qb->getQuery()->execute();
    }
} 
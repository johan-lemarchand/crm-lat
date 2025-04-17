<?php

namespace App\Command\Infrastructure\Repository;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Entity\Command;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineCommandRepository implements CommandRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->repository = $entityManager->getRepository(Command::class);
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function find(int $id): ?Command
    {
        return $this->repository->find($id);
    }

    public function save(Command $command): void
    {
        $this->entityManager->persist($command);
        $this->entityManager->flush();
    }

    public function remove(Command $command): void
    {
        $this->entityManager->remove($command);
        $this->entityManager->flush();
    }
} 
<?php

namespace App\Command\Domain\Repository;

use App\Entity\Command;
use App\Entity\CommandExecution;

interface CommandExecutionRepositoryInterface
{
    public function findByCommand(int $commandId): array;
    public function save(CommandExecution $execution): void;
    public function find(int $id): ?CommandExecution;
    public function findOneBy(array $criteria, array $orderBy = null): ?CommandExecution;
    
    public function deleteExecutionLogs(
        Command $command,
        ?\DateTime $startDate = null,
        ?\DateTime $endDate = null,
        ?int $lastExecutionId = null
    ): void;
} 
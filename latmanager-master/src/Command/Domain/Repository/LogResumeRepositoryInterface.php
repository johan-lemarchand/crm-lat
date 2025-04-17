<?php

namespace App\Command\Domain\Repository;

use App\Entity\Command;
use App\Entity\LogResume;

interface LogResumeRepositoryInterface
{
    public function findOneBy(array $criteria, ?array $orderBy = null): ?LogResume;
    
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
    
    public function deleteLogResumes(
        Command $command,
        ?\DateTime $startDate = null,
        ?\DateTime $endDate = null,
        ?\DateTime $lastExecutionDate = null
    ): void;
} 
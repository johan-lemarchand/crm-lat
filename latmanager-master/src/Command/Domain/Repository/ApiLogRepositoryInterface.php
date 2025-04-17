<?php

namespace App\Command\Domain\Repository;

use App\Entity\Command;

interface ApiLogRepositoryInterface
{
    public function deleteApiLogs(
        Command $command,
        ?\DateTime $startDate = null,
        ?\DateTime $endDate = null
    ): void;
} 
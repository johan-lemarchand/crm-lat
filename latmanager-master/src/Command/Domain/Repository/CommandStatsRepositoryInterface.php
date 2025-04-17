<?php

namespace App\Command\Domain\Repository;

use App\Entity\Command;

interface CommandStatsRepositoryInterface
{
    public function getExecutionSize(Command $command): int;
    public function getApiLogsSize(Command $command): int;
    public function getResumeSize(Command $command): int;
    public function getTotalLogs(Command $command): int;
    public function getExecutionPeriod(Command $command): array;
} 
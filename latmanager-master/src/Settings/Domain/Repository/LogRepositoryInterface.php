<?php

namespace App\Settings\Domain\Repository;

interface LogRepositoryInterface
{
    public function getCommandsWithLogs(): array;
    public function clearDbLogs(?string $commandId = null, ?string $logType = null): void;
} 
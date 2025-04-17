<?php

namespace App\Command\Application\Query\GetCommandLogs;

readonly class GetCommandLogsQuery
{
    public function __construct(
        private int $commandId
    ) {}

    public function getCommandId(): int
    {
        return $this->commandId;
    }
} 
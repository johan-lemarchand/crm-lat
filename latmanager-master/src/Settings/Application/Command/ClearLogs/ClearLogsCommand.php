<?php

namespace App\Settings\Application\Command\ClearLogs;

readonly class ClearLogsCommand
{
    public function __construct(
        private string $type,
        private ?string $commandId = null,
        private ?string $logType = null
    ) {}

    public function getType(): string
    {
        return $this->type;
    }

    public function getCommandId(): ?string
    {
        return $this->commandId;
    }

    public function getLogType(): ?string
    {
        return $this->logType;
    }
} 
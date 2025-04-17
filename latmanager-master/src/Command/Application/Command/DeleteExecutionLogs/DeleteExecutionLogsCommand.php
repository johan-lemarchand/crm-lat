<?php

namespace App\Command\Application\Command\DeleteExecutionLogs;

readonly class DeleteExecutionLogsCommand
{
    public function __construct(
        private int $commandId,
        private ?\DateTime $startDate = null,
        private ?\DateTime $endDate = null,
        private ?int $lastExecutionId = null
    ) {}

    public function getCommandId(): int
    {
        return $this->commandId;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function getLastExecutionId(): ?int
    {
        return $this->lastExecutionId;
    }
} 
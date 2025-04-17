<?php

namespace App\Command\Application\Command\ClearHistoryLogs;

readonly class ClearHistoryLogsCommand
{
    public function __construct(
        private int $commandId,
        private ?\DateTime $startDate = null,
        private ?\DateTime $endDate = null
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
} 
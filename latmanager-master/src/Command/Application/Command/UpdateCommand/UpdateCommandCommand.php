<?php

namespace App\Command\Application\Command\UpdateCommand;

readonly class UpdateCommandCommand
{
    public function __construct(
        private int $id,
        private string $name,
        private string $scriptName,
        private ?string $startTime,
        private ?string $endTime,
        private string $recurrence,
        private bool $active,
        private ?int $interval = null,
        private ?int $attemptMax = null,
        private ?bool $statusSendEmail = null
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getScriptName(): string
    {
        return $this->scriptName;
    }

    public function getStartTime(): ?string
    {
        return $this->startTime;
    }

    public function getEndTime(): ?string
    {
        return $this->endTime;
    }

    public function getRecurrence(): string
    {
        return $this->recurrence;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getInterval(): ?int
    {
        return $this->interval;
    }

    public function getAttemptMax(): ?int
    {
        return $this->attemptMax;
    }

    public function getStatusSendEmail(): ?bool
    {
        return $this->statusSendEmail;
    }
} 
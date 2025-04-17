<?php

namespace App\Command\Application\Command\CheckScheduler;

readonly class CheckSchedulerCommand
{
    public function __construct(
        private int $id,
        private ?string $emailHtml = null
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmailHtml(): ?string
    {
        return $this->emailHtml;
    }
} 
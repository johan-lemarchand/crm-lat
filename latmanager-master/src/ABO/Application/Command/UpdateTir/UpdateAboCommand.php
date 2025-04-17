<?php

namespace App\ABO\Application\Command\UpdateTir;

readonly class UpdateAboCommand
{
    public function __construct(
        private int $tirId,
        private string $tirCode,
        private string $tirSocieteType,
        private string $tirSociete,
        private string $pcvnum,
        private string $user
    )
    {
    }

    public function getTirId(): int
    {
        return $this->tirId;
    }

    public function getTirCode(): string
    {
        return $this->tirCode;
    }

    public function getTirSocieteType(): string
    {
        return $this->tirSocieteType;
    }

    public function getTirSociete(): string
    {
        return $this->tirSociete;
    }

    public function getPcvnum(): string
    {
        return $this->pcvnum;
    }

    public function getUser(): string
    {
        return $this->user;
    }
} 
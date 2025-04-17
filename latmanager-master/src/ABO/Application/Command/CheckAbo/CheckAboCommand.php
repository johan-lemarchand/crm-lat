<?php

namespace App\ABO\Application\Command\CheckAbo;

readonly class CheckAboCommand
{
    public function __construct(
        private string $pcvnum,
        private string $user
    )
    {
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
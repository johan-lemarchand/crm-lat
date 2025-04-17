<?php

namespace App\ABO\Application\Command\CheckCodeClient;

readonly class CheckCodeClientCommand
{
    public function __construct(
        private string $user,
        private string $codeClient,
        private string $pcvnum,
        private string $type
    )
    {
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getCodeClient(): string
    {
        return $this->codeClient;
    }

    public function getPcvnum(): string
    {
        return $this->pcvnum;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
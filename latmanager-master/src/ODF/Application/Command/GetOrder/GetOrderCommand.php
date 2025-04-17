<?php

namespace App\ODF\Application\Command\GetOrder;

readonly class GetOrderCommand
{
    public function __construct(
        private int $pcdid,
        private string $user
    ) {}

    public function getPcdid(): int
    {
        return $this->pcdid;
    }

    public function getUser(): string
    {
        return $this->user;
    }
} 
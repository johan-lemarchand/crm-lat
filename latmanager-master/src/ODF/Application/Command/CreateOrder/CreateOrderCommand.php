<?php

namespace App\ODF\Application\Command\CreateOrder;

readonly class CreateOrderCommand
{
    public function __construct(
        public int $pcdid,
        public string $user,
        public string $pcdnum,
    ) {}

    public function getPcdid(): int
    {
        return $this->pcdid;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getPcdnum(): string
    {
        return $this->pcdnum;
    }
}

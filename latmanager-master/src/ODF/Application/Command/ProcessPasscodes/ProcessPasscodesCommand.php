<?php

namespace App\ODF\Application\Command\ProcessPasscodes;

readonly class ProcessPasscodesCommand
{
    public function __construct(
        public int    $pcdid,
        public string $user,
        public string $orderNumber,
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

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getPcdnum(): string
    {
        return $this->pcdnum;
    }
}

<?php

namespace App\ODF\Application\Command\ValidateOrder;

readonly class ValidateOrderCommand
{
    public function __construct(
        public int $pcdid,
        public string $pcdnum,
        public string $user,
        public ?string $uniqueId = null
    ) {}

    public function getPcdid(): int
    {
        return $this->pcdid;
    }

    public function getPcdnum(): string
    {
        return $this->pcdnum;
    }

    public function getUser(): string
    {
        return $this->user;
    }
}

<?php

namespace App\ODF\Application\Command\CreateManufacturingOrder;

readonly class CreateManufacturingOrderCommand
{
    public function __construct(
        private int    $pcdid,
        private string $orderNumber,
        private array  $activationResult,
        private string $user
    ) {
    }

    public function getPcdid(): int
    {
        return $this->pcdid;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getActivationResult(): array
    {
        return $this->activationResult;
    }

    public function getUser(): string
    {
        return $this->user;
    }
}

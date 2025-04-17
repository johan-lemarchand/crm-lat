<?php

namespace App\ODF\Application\Command\ProcessTransfert;

readonly class ProcessTransfertCommand
{
    public function __construct(
        private array $items,
        private array $affaire
    ) {}

    public function getItems(): array
    {
        return $this->items;
    }

    public function getAffaire(): array
    {
        return $this->affaire;
    }
}

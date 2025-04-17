<?php

namespace App\ODF\Application\Command\CheckAffaire;

readonly class CheckAffaireCommand
{
    public function __construct(
        private int $pcdid
    ) {
    }

    public function getPcdid(): int
    {
        return $this->pcdid;
    }
} 
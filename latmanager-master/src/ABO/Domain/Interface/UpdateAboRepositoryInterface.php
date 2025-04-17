<?php

namespace App\ABO\Domain\Interface;

interface UpdateAboRepositoryInterface
{
    public function updateTIRID(string $tirId, string $pcvnum): void;
} 
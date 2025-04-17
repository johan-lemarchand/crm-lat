<?php

namespace App\ABO\Domain\Interface;

interface UpdateAboServiceInterface
{
    public function updateAbo(int $tirId, string $tirCode, string $tirSocieteType, string $tirSociete): array;
} 
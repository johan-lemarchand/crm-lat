<?php

namespace App\ABO\Domain\Interface;

interface CheckAboServiceInterface
{
    public function checkAbo(array $pieceDetails): array;
} 
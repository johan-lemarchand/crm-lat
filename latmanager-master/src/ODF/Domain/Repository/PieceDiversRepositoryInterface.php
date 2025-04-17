<?php

namespace App\ODF\Domain\Repository;

interface PieceDiversRepositoryInterface
{
    public function updateStatus(int $pcdid, string $user, array $data): void;
} 
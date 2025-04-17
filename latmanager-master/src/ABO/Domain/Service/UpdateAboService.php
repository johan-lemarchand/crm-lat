<?php

namespace App\ABO\Domain\Service;

use App\ABO\Domain\Interface\UpdateAboRepositoryInterface;
use App\ABO\Domain\Interface\UpdateAboServiceInterface;

readonly class UpdateAboService implements UpdateAboServiceInterface
{
    public function __construct(
        private UpdateAboRepositoryInterface $repository
    ) {}
    
    public function updateAbo(int $tirId, string $tirCode, string $tirSocieteType, string $tirSociete): array
    {
        return $this->repository->updateAbo($tirId, $tirCode, $tirSocieteType, $tirSociete);
    }
} 
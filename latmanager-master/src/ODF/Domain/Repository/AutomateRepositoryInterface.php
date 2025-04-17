<?php

namespace App\ODF\Domain\Repository;

interface AutomateRepositoryInterface
{
    public function deleteAutomate(string $pcdnum): void;
} 
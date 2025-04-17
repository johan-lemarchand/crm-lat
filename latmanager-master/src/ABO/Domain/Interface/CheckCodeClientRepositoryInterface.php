<?php

namespace App\ABO\Domain\Interface;

interface CheckCodeClientRepositoryInterface
{
    public function findCodeClient(string $codeClient): array;
}

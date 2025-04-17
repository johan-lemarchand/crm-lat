<?php

namespace App\Command\Domain\Repository;

use App\Entity\Command;

interface CommandRepositoryInterface
{
    /**
     * @return array<Command>
     */
    public function findAll(): array;
    
    public function find(int $id): ?Command;
    
    public function save(Command $command): void;
    
    public function remove(Command $command): void;
} 
<?php

namespace App\Command\Application\Command\ExecuteCommand;

readonly class ExecuteCommandCommand
{
    public function __construct(
        private int $id,
        private array $parameters = []
    ) {}

    public function getId(): int
    {
        return $this->id;
    }
    
    public function getParameters(): array
    {
        return $this->parameters;
    }
} 
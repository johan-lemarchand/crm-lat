<?php

namespace App\Command\Application\Command\DeleteCommand;

readonly class DeleteCommandCommand
{
    public function __construct(
        private int $id
    ) {}

    public function getId(): int
    {
        return $this->id;
    }
} 
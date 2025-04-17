<?php

namespace App\Command\Application\Query\GetExecutionStatus;

readonly class GetExecutionStatusQuery
{
    public function __construct(
        private int $executionId
    ) {}

    public function getExecutionId(): int
    {
        return $this->executionId;
    }
} 
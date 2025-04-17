<?php

namespace App\ABO\Application\Query\GetPctcode;

class GetPctcodeQuery
{
    public function __construct(
        private readonly int $pinidorg = 21
    ) {}

    public function getPinidorg(): int
    {
        return $this->pinidorg;
    }
} 
<?php

namespace App\ODF\Application\Command\CheckStep;

class CheckStepCommand
{
    public function __construct(
        private readonly string $pcdid,
        private readonly string $pcdnum,
        private readonly string $step
    ) {}

    public function getPcdid(): string
    {
        return $this->pcdid;
    }

    public function getPcdnum(): string
    {
        return $this->pcdnum;
    }

    public function getStep(): string
    {
        return $this->step;
    }
} 
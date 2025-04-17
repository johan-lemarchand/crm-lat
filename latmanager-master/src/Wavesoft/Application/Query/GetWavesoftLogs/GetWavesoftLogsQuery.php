<?php

namespace App\Wavesoft\Application\Query\GetWavesoftLogs;

class GetWavesoftLogsQuery
{
    public function __construct(
        private readonly ?int $limit = null
    ) {
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }
} 
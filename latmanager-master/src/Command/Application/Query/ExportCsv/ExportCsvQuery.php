<?php

namespace App\Command\Application\Query\ExportCsv;

readonly class ExportCsvQuery
{
    public function __construct(
        private int $id,
        private string $type
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }
} 
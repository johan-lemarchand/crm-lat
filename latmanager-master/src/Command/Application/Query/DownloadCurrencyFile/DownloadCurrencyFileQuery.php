<?php

namespace App\Command\Application\Query\DownloadCurrencyFile;

readonly class DownloadCurrencyFileQuery
{
    public function __construct(
        private string $filename
    ) {}

    public function getFilename(): string
    {
        return $this->filename;
    }
} 
<?php

namespace App\Command\Application\Query\DownloadCurrencyFile;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\HttpFoundation\File\File;

#[AsMessageHandler]
readonly class DownloadCurrencyFileHandler
{
    public function __construct(
        private string $projectDir
    ) {}

    public function __invoke(DownloadCurrencyFileQuery $query): File
    {
        $filePath = $this->projectDir . '/var/currency/' . $query->getFilename();

        if (!file_exists($filePath)) {
            throw new \Exception('Le fichier demandé n\'existe pas');
        }

        return new File($filePath);
    }
} 
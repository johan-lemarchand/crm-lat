<?php

namespace App\Command\Application\Query\ListCurrencyFiles;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Finder\Finder;

#[AsMessageHandler]
readonly class ListCurrencyFilesHandler
{
    public function __construct(
        private string $projectDir
    ) {}

    public function __invoke(ListCurrencyFilesQuery $query): array
    {
        $csvDirectory = $this->projectDir . '/var/currency';
        $files = [];

        if (!is_dir($csvDirectory)) {
            return $files;
        }

        $finder = new Finder();
        $finder->files()->in($csvDirectory)->name('*.csv')->sortByModifiedTime();

        foreach ($finder as $file) {
            $files[] = [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime())
            ];
        }

        return $files;
    }
} 
<?php

namespace App\Command\Application\Query\ExportCsv;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Service\CsvExportService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[AsMessageHandler]
readonly class ExportCsvHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository,
        private CsvExportService $csvExportService
    ) {}

    public function __invoke(ExportCsvQuery $query): StreamedResponse
    {
        $command = $this->commandRepository->find($query->getId());
        
        if (!$command) {
            throw new \RuntimeException('Command not found');
        }

        return $this->csvExportService->exportCommand($command, $query->getType());
    }
} 
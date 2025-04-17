<?php

namespace App\Command\Application\Query\GetExecutionStatus;

use App\Command\Domain\Repository\CommandExecutionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class GetExecutionStatusHandler
{
    public function __construct(
        private CommandExecutionRepositoryInterface $executionRepository
    ) {}

    public function __invoke(GetExecutionStatusQuery $query): array
    {
        $execution = $this->executionRepository->find($query->getExecutionId());
        
        if (!$execution) {
            throw new \RuntimeException('Execution not found');
        }

        return [
            'status' => $execution->getStatus(),
            'context' => [
                'output' => $execution->getOutput(),
                'error' => $execution->getError(),
            ],
        ];
    }
} 
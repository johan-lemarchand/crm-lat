<?php

namespace App\Command\Application\Query\GetCommandLogs;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Command\Domain\Repository\CommandExecutionRepositoryInterface;
use App\Command\Domain\Repository\LogResumeRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class GetCommandLogsHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository,
        private CommandExecutionRepositoryInterface $executionRepository,
        private LogResumeRepositoryInterface $logResumeRepository
    ) {}

    public function __invoke(GetCommandLogsQuery $query): array
    {
        $command = $this->commandRepository->find($query->getCommandId());
        
        if (!$command) {
            throw new \RuntimeException('Command not found');
        }

        $executions = $this->executionRepository->findByCommand($command->getId());
        
        return array_map(function($execution) {
            $logResume = $this->logResumeRepository->findOneBy([
                'command' => $execution->getCommand(),
                'executionDate' => $execution->getStartedAt()
            ]);

            $formatted = [
                'id' => $execution->getId(),
                'startedAt' => $execution->getStartedAt()?->format('Y-m-d H:i:s'),
                'endedAt' => $execution->getEndedAt()?->format('Y-m-d H:i:s'),
                'status' => $execution->getStatus(),
                'output' => $execution->getOutput(),
                'error' => $execution->getError(),
                'apiLogs' => array_map(function($apiLog) {
                    return [
                        'id' => $apiLog->getId(),
                        'endpoint' => $apiLog->getEndpoint(),
                        'method' => $apiLog->getMethod(),
                        'statusCode' => $apiLog->getStatusCode(),
                        'duration' => $apiLog->getDuration(),
                        'createdAt' => $apiLog->getCreatedAt()?->format('Y-m-d H:i:s'),
                        'requestXml' => $apiLog->getRequestXml(),
                        'responseXml' => $apiLog->getResponseXml()
                    ];
                }, $execution->getApiLogs()->toArray())
            ];

            if ($logResume) {
                $resume = json_decode($logResume->getResume(), true);
                $formatted['resume'] = $resume;
                $formatted['status'] = $resume['statistiques']['status_command'] ?? $formatted['status'];
            }

            return $formatted;
        }, $executions);
    }
} 
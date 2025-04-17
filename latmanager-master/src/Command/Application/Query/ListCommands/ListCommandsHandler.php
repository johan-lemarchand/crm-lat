<?php

namespace App\Command\Application\Query\ListCommands;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Command\Domain\Repository\CommandExecutionRepositoryInterface;
use App\Command\Domain\Repository\CommandStatsRepositoryInterface;
use App\Service\FormatService;
use App\Service\VersionManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Command;

#[AsMessageHandler]
readonly class ListCommandsHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository,
        private CommandExecutionRepositoryInterface $commandExecutionRepository,
        private CommandStatsRepositoryInterface $statsRepository,
        private VersionManager $versionManager,
        private EntityManagerInterface $entityManager,
        private FormatService $formatService
    ) {}

    public function __invoke(ListCommandsQuery $query): array
    {
        $commands = $this->commandRepository->findAll();
        $versions = $this->versionManager->getVersions();
        $data = [];
        $schedulerCommand = null;

        foreach ($commands as $command) {
            if ('check-scheduler' === $command->getScriptName()) {
                $schedulerCommand = $this->formatSchedulerCommand($command);
                continue;
            }

            $stats = $this->getStats($command);
            $data[] = $this->formatCommand($command, $stats, $versions);
        }

        return [
            'commands' => $data,
            'schedulerCommand' => $schedulerCommand,
            'versions' => $versions
        ];
    }

    private function formatSchedulerCommand(Command $command): array
    {
        return [
            'id' => $command->getId(),
            'name' => $command->getName(),
            'scriptName' => $command->getScriptName(),
            'lastExecutionDate' => $command->getLastExecutionDate()?->format('Y-m-d H:i:s'),
            'lastStatus' => $command->getLastStatus(),
            'statusScheduler' => $command->getStatusScheduler(),
        ];
    }

    private function formatCommand(Command $command, array $stats, array $versions): array
    {
        return [
            'id' => $command->getId(),
            'name' => $command->getName(),
            'scriptName' => $command->getScriptName(),
            'recurrence' => $command->getRecurrence(),
            'interval' => $command->getInterval(),
            'attemptMax' => $command->getAttemptMax(),
            'lastExecutionDate' => $command->getLastExecutionDate()?->format('Y-m-d H:i:s'),
            'nextExecutionDate' => $command->getNextExecutionDate()?->format('Y-m-d H:i:s'),
            'lastStatus' => $command->getLastStatus(),
            'startTime' => $command->getStartTime()?->format('c'),
            'endTime' => $command->getEndTime()?->format('c'),
            'active' => $command->getStatusScheduler(),
            'size' => $stats['size']['formatted'],
            'total_logs' => $stats['total_logs'],
            'details' => $stats['details'],
            'statusScheduler' => $command->getStatusScheduler() ? 'Activée' : 'Désactivée',
            'statusSendEmail' => $command->getStatusSendEmail(),
            'manualExecutionDate' => $command->getManualExecutionDate()?->format('Y-m-d H:i:s'),
            'versions' => $versions
        ];
    }

    private function getStats(Command $command): array
    {
        try {
            $executionSize = $this->statsRepository->getExecutionSize($command);
            $apiSize = $this->statsRepository->getApiLogsSize($command);
            $resumeSize = $this->statsRepository->getResumeSize($command);
            $totalSize = $executionSize + $apiSize + $resumeSize;
            $totalLogs = $this->statsRepository->getTotalLogs($command);
            $period = $this->statsRepository->getExecutionPeriod($command);

            $periodText = '';
            if ($period['firstDate'] && $period['lastDate']) {
                $interval = $period['firstDate']->diff($period['lastDate']);
                $days = $interval->days + 1;
                
                if ($period['firstDate']->format('d/m/Y') === $period['lastDate']->format('d/m/Y')) {
                    $periodText = sprintf('%s (%d jour)', $period['firstDate']->format('d/m/Y'), $days);
                } else {
                    $periodText = sprintf('%s - %s (%d jours)', 
                        $period['firstDate']->format('d/m/Y'), 
                        $period['lastDate']->format('d/m/Y'),
                        $days
                    );
                }
            }

            return [
                'size' => [
                    'bytes' => $totalSize,
                    'formatted' => $this->formatService->formatBytes($totalSize)
                ],
                'total_logs' => $totalLogs,
                'details' => [
                    'execution' => $this->formatService->formatBytes($executionSize),
                    'api' => $this->formatService->formatBytes($apiSize),
                    'resume' => $this->formatService->formatBytes($resumeSize),
                    'period' => $periodText ?: 'Aucune donnée'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'size' => [
                    'bytes' => 0,
                    'formatted' => '0 B'
                ],
                'total_logs' => 0,
                'details' => [
                    'execution' => '0 B',
                    'api' => '0 B',
                    'resume' => '0 B',
                    'period' => 'Aucune donnée'
                ]
            ];
        }
    }
} 
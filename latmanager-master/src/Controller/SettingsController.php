<?php

namespace App\Controller;

use App\Settings\Application\Command\ClearLogs\ClearLogsCommand;
use App\Settings\Application\Query\GetLogs\GetLogsQuery;
use App\Settings\Application\Query\GetDbCommands\GetDbCommandsQuery;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/api')]
class SettingsController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.query')]
        private readonly MessageBusInterface $queryBus,
        #[Autowire(service: 'messenger.bus.command')]
        private readonly MessageBusInterface $commandBus,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    ) {}


    #[Route('/settings/logs', name: 'api_settings_logs', methods: ['GET'])]
    public function getLogs(): JsonResponse
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetLogsQuery());
            return $this->json($envelope->last(HandledStamp::class)->getResult());
        } catch (\Exception | ExceptionInterface $e) {
            $this->logger->error('Erreur lors de la récupération des logs', [
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/settings/logs/{type}', name: 'api_settings_clear_logs', methods: ['DELETE'])]
    public function clearLogs(string $type, Request $request): JsonResponse
    {
        try {
            $commandId = $request->query->get('commandId');
            $logType = $request->query->get('logType');

            $envelope = $this->commandBus->dispatch(new ClearLogsCommand($type, $commandId, $logType));
            return $this->json($envelope->last(HandledStamp::class)->getResult());
        } catch (\Exception | ExceptionInterface $e) {
            $this->logger->error('Erreur lors de la suppression des logs', [
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/settings/logs/db/commands', name: 'api_settings_db_commands', methods: ['GET'])]
    public function getDbCommands(): JsonResponse
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetDbCommandsQuery());
            return $this->json($envelope->last(HandledStamp::class)->getResult());
        } catch (\Exception | ExceptionInterface $e) {
            $this->logger->error('Erreur lors de la récupération des commandes', [
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/settings/changelog', name: 'api_settings_changelog', methods: ['GET'])]
    public function getChangelog(): JsonResponse
    {
        try {
            $changelogPath = $this->projectDir.'/CHANGELOG.md';
            
            if (!file_exists($changelogPath)) {
                return $this->json(['content' => '# Changelog']);
            }

            $content = file_get_contents($changelogPath);
            return $this->json(['content' => $content]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération du changelog', [
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

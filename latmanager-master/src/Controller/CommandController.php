<?php

namespace App\Controller;

use App\Command\Application\Query\ListCommands\ListCommandsQuery;
use App\Entity\Command;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use App\Command\Application\Command\ExecuteCommand\ExecuteCommandCommand;
use App\Command\Application\Query\ExportCsv\ExportCsvQuery;
use App\Command\Application\Query\ListCurrencyFiles\ListCurrencyFilesQuery;
use App\Command\Application\Query\DownloadCurrencyFile\DownloadCurrencyFileQuery;
use App\Command\Application\Command\CheckScheduler\CheckSchedulerCommand;
use App\Command\Application\Command\DeleteExecutionLogs\DeleteExecutionLogsCommand;
use App\Command\Application\Command\DeleteApiLogs\DeleteApiLogsCommand;
use App\Command\Application\Query\GetExecutionStatus\GetExecutionStatusQuery;
use App\Command\Application\Query\GetCommandLogs\GetCommandLogsQuery;
use App\Command\Application\Command\CreateCommand\CreateCommandCommand;
use App\Command\Application\Command\UpdateCommand\UpdateCommandCommand;
use App\Command\Application\Command\DeleteCommand\DeleteCommandCommand;
use App\Command\Application\Command\ClearAllLogs\ClearAllLogsCommand;
use App\Command\Application\Command\ClearHistoryLogs\ClearHistoryLogsCommand;
use App\Command\Application\Command\ClearApiLogs\ClearApiLogsCommand;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Command\Application\Command\ExecuteCommand\ExecuteCommandHandler;
use App\Command\Application\Query\GetAllCommand\GetAllCommandQuery;
use App\Command\Application\Query\GetCommand\GetCommandQuery;
use App\Command\Application\Query\GetCurrencyFiles\GetCurrencyFilesQuery;
use App\Command\Application\Command\DeleteSessionLogs\DeleteSessionLogsCommand;
use App\Command\Application\Query\CheckScheduler\CheckSchedulerQuery;
use App\Command\Application\Command\ExportLogs\ExportLogsCommand;

#[Route('/api')]
class CommandController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
        private readonly ExecuteCommandHandler $executeCommandHandler
    ) {}

    #[Route('/commands', name: 'api_commands_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            $envelope = $this->queryBus->dispatch(new ListCommandsQuery());
            return $this->json($envelope->last(HandledStamp::class)->getResult());
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/commands/{id}', name: 'api_command_get', methods: ['GET'])]
    public function get(Command $command): JsonResponse
    {
        return $this->json([
            'id' => $command->getId(),
            'name' => $command->getName(),
            'scriptName' => $command->getScriptName(),
            'recurrence' => $command->getRecurrence(),
            'interval' => $command->getInterval(),
            'attemptMax' => $command->getAttemptMax(),
            'lastExecutionDate' => $command->getLastExecutionDate()?->format('Y-m-d H:i:s'),
            'lastStatus' => $command->getLastStatus(),
            'startTime' => $command->getStartTime()?->format('c'),
            'endTime' => $command->getEndTime()?->format('c'),
            'active' => $command->isActive(),
            'statusSendEmail' => $command->getStatusSendEmail(),
        ]);
    }

    #[Route('/commands', name: 'api_command_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $envelope = $this->commandBus->dispatch(new CreateCommandCommand(
                $data['name'],
                $data['scriptName'],
                $data['startTime'] ?? null,
                $data['endTime'] ?? null,
                $data['recurrence'],
                $data['active'] ?? false,
                $data['interval'] ?? null,
                $data['attemptMax'] ?? null,
                $data['statusSendEmail'] ?? false
            ));

            return $this->json($envelope->last(HandledStamp::class)->getResult(), Response::HTTP_CREATED);
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(
                ['error' => 'Erreur lors de la création de la commande : '.$e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/commands/{id}/execute', name: 'api_commands_execute', methods: ['POST'])]
    public function execute(int $id, Request $request): StreamedResponse
    {
        // Récupérer les paramètres de la requête
        $parameters = json_decode($request->getContent(), true) ?? [];
        
        // Créer une réponse streamée
        $response = new StreamedResponse(function () use ($id, $parameters) {
            try {
                // Passer l'ID et les paramètres au handler
                $command = new ExecuteCommandCommand($id, $parameters);
                
                // Exécuter le handler en mode streaming
                $this->executeCommandHandler->executeWithStreaming($command, function ($data) {
                    echo 'data: ' . json_encode($data) . "\n\n";
                    ob_flush();
                    flush();
                });
                
            } catch (\Throwable $e) {
                echo 'data: ' . json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]) . "\n\n";
                ob_flush();
                flush();
            }
        });

        // Configurer les en-têtes pour le streaming
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    #[Route('/commands/{id}', name: 'api_command_update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $envelope = $this->commandBus->dispatch(new UpdateCommandCommand(
                $id,
                $data['name'],
                $data['scriptName'],
                $data['startTime'] ?? null,
                $data['endTime'] ?? null,
                $data['recurrence'],
                $data['active'],
                $data['interval'] ?? null,
                $data['attemptMax'] ?? null,
                $data['statusSendEmail'] ?? null
            ));

            return $this->json($envelope->last(HandledStamp::class)->getResult());
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(
                ['error' => 'Erreur lors de la mise à jour de la commande : '.$e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/commands/{id}', name: 'api_command_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $envelope = $this->commandBus->dispatch(new DeleteCommandCommand($id));
            return $this->json([
                'success' => true,
                'message' => 'Commande supprimée avec succès'
            ]);
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(
                ['error' => 'Erreur lors de la suppression de la commande : '.$e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/commands/{id}/logs', name: 'api_command_logs', methods: ['GET'])]
    public function getLogs(int $id): JsonResponse
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetCommandLogsQuery($id));
            return $this->json($envelope->last(HandledStamp::class)->getResult());
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/commands/{id}/logs/clear/all', name: 'api_command_clear_all_logs', methods: ['DELETE'])]
    public function clearAllLogs(Request $request, int $id): JsonResponse
    {
        try {
            $startDate = $request->query->get('startDate') ? new \DateTime($request->query->get('startDate')) : null;
            $endDate = $request->query->get('endDate') ? new \DateTime($request->query->get('endDate')) : null;

            $envelope = $this->commandBus->dispatch(new ClearAllLogsCommand($id, $startDate, $endDate));
            return $this->json($envelope->last(HandledStamp::class)->getResult());
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/commands/{id}/logs/clear/history', name: 'api_command_clear_history_logs', methods: ['DELETE'])]
    public function clearHistoryLogs(Request $request, int $id): JsonResponse
    {
        try {
            $startDate = $request->query->get('startDate') ? new \DateTime($request->query->get('startDate')) : null;
            $endDate = $request->query->get('endDate') ? new \DateTime($request->query->get('endDate')) : null;

            $envelope = $this->commandBus->dispatch(new ClearHistoryLogsCommand($id, $startDate, $endDate));
            return $this->json($envelope->last(HandledStamp::class)->getResult());
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/commands/{id}/logs/clear/api', name: 'api_command_clear_api_logs', methods: ['DELETE'])]
    public function clearApiLogs(Request $request, int $id): JsonResponse
    {
        try {
            $startDate = $request->query->get('startDate') ? new \DateTime($request->query->get('startDate')) : null;
            $endDate = $request->query->get('endDate') ? new \DateTime($request->query->get('endDate')) : null;

            $envelope = $this->commandBus->dispatch(new ClearApiLogsCommand($id, $startDate, $endDate));
            return $this->json($envelope->last(HandledStamp::class)->getResult());
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/commands/{id}/logs/execution', name: 'api_command_delete_execution_logs', methods: ['DELETE'])]
    public function deleteExecutionLogs(Request $request, int $id): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $startDate = isset($data['startDate']) ? new \DateTime($data['startDate']) : null;
            $endDate = isset($data['endDate']) ? new \DateTime($data['endDate']) : null;
            $lastExecutionId = $data['lastExecutionId'] ?? null;

            $envelope = $this->commandBus->dispatch(
                new DeleteExecutionLogsCommand($id, $startDate, $endDate, $lastExecutionId)
            );
            $result = $envelope->last(HandledStamp::class)->getResult();

            return $this->json($result);
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/commands/{id}/logs/api', name: 'api_command_delete_api_logs', methods: ['DELETE'])]
    public function deleteApiLogs(Request $request, int $id): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $startDate = isset($data['startDate']) ? new \DateTime($data['startDate']) : null;
            $endDate = isset($data['endDate']) ? new \DateTime($data['endDate']) : null;

            $envelope = $this->commandBus->dispatch(
                new DeleteApiLogsCommand($id, $startDate, $endDate)
            );
            $result = $envelope->last(HandledStamp::class)->getResult();

            return $this->json($result);
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/commands/executions/{id}/status', name: 'api_command_execution_status', methods: ['GET'])]
    public function getExecutionStatus(int $id): JsonResponse
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetExecutionStatusQuery($id));
            return $this->json($envelope->last(HandledStamp::class)->getResult());
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/commands/{id}/check-scheduler', name: 'api_command_check_scheduler', methods: ['POST'])]
    public function checkScheduler(Request $request, int $id): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $emailHtml = $data['emailHtml'] ?? null;

            $envelope = $this->commandBus->dispatch(new CheckSchedulerCommand($id, $emailHtml));
            $result = $envelope->last(HandledStamp::class)->getResult();

            return $this->json($result);
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(
                ['error' => 'Erreur lors de la vérification du statut du scheduler : '.$e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/commands/{id}/export/{type}', name: 'api_commands_export', methods: ['GET'])]
    public function export(int $id, string $type): Response
    {
        try {
            $envelope = $this->queryBus->dispatch(new ExportCsvQuery($id, $type));
            return $envelope->last(HandledStamp::class)->getResult();
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/commands/{id}/currency-files', name: 'api_command_currency_files', methods: ['GET'])]
    public function getCurrencyFiles(int $id): JsonResponse
    {
        try {
            $envelope = $this->queryBus->dispatch(new ListCurrencyFilesQuery($id));
            $files = $envelope->last(HandledStamp::class)->getResult();
            
            return $this->json($files);
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/files/currency', name: 'api_currency_files', methods: ['GET'])]
    public function listCurrencyFiles(): JsonResponse
    {
        try {
            $envelope = $this->queryBus->dispatch(new ListCurrencyFilesQuery());
            $files = $envelope->last(HandledStamp::class)->getResult();
            
            return $this->json($files);
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/files/currency/{filename}', name: 'api_currency_file_download', methods: ['GET'])]
    public function downloadCurrencyFile(string $filename): Response
    {
        try {
            $envelope = $this->queryBus->dispatch(new DownloadCurrencyFileQuery($filename));
            $file = $envelope->last(HandledStamp::class)->getResult();
            
            return $this->file($file, null, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        } catch (\Exception | ExceptionInterface $e) {
            throw $this->createNotFoundException($e->getMessage());
        }
    }
}

<?php

namespace App\ODF\Application\Command\ValidateOrder;

use App\ODF\Domain\Service\UniqueIdServiceInterface;
use App\ODF\Infrastructure\Service\Timer;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\ODF\Domain\Service\AutomateServiceInterface;
use App\ODF\Domain\Repository\PieceDetailsRepositoryInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use App\ODF\Application\Command\CheckArticles\CheckArticlesCommand;
use App\ODF\Application\Command\CheckAffaire\CheckAffaireCommand;
use App\ODF\Application\Command\ProcessArticles\ProcessArticlesCommand;
use App\ODF\Application\Command\ProcessTransfert\ProcessTransfertCommand;
use App\ODF\Application\Command\UpdateMemoAndApi\UpdateMemoAndApiCommand;

#[AsMessageHandler]
readonly class ValidateOrderCommandHandler
{
    public function __construct(
        private UniqueIdServiceInterface            $uniqueIdService,
        private PieceDetailsRepositoryInterface     $pieceDetailsRepository,
        private AutomateServiceInterface            $automateService,
        private Timer                               $timer,
        private MessageBusInterface                 $commandBus,
        private OdfExecutionLogger                  $executionLogger
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(ValidateOrderCommand $command): array
    {
        $handlerStartTime = $this->executionLogger->startHandler(
            'ValidateOrderCommandHandler',
            ['pcdid' => $command->getPcdid(), 'pcdnum' => $command->getPcdnum()]
        );

        $pcdid = $command->getPcdid();
        $pcdnum = $command->getPcdnum();
        $user = $command->getUser();

        try {
            // Log de la recherche dans pieceDetails
            $pieceDetails = $this->pieceDetailsRepository->findByPcdid($pcdid);
            $this->executionLogger->addStep(
                description: 'Recherche PieceDetails',
                status: empty($pieceDetails) ? 'error' : 'success',
                message: empty($pieceDetails) ? 'Aucun détail trouvé' : 'Détails trouvés'
            );

            if (empty($pieceDetails)) {
                throw new \Exception('Aucun détail trouvé pour cette pièce');
            }

            // Vérification de l'ID unique
            $uniqueIdStartTime = microtime(true);
            $uniqueIdResult = $this->uniqueIdService->checkCloseOdfAndUniqueId($pcdid);
            $this->executionLogger->addStep(
                description: 'Vérification ID unique',
                status: $uniqueIdResult['status'] ?? 'success',
                message: json_encode($uniqueIdResult),
                stepStartTime: $uniqueIdStartTime
            );

            $memoId = $pieceDetails[0]['MEMOID'] ?? throw new \Exception('MemoId non trouvé');

            if ($uniqueIdResult['isClosed'] ?? false) {
                $this->executionLogger->finish(
                    description: 'ODF clôturé',
                    message: json_encode($uniqueIdResult['messages']),
                    stepStatus: 1,
                    user: $user,
                );
                return [
                    'status' => 'error',
                    'currentStep' => 'Vérification initiale',
                    'progress' => 100,
                    'isClosed' => true,
                    'messages' => $uniqueIdResult['messages']
                ];
            }

            // Si l'ODF a déjà un ID unique (en cours)
            if ($uniqueIdResult['exists'] && $uniqueIdResult['uniqueId']) {
                $message = "L'ODF est déjà validé avec l'ID : " . $uniqueIdResult['uniqueId'];
                $this->timer->logStep(
                    "ODF déjà validé",
                    'info',
                    'success',
                    $message
                );
                return [
                    'status' => 'success',
                    'currentStep' => 'ODF déjà validé',
                    'progress' => 100,
                    'messages' => [[
                        'type' => 'info',
                        'message' => $message,
                        'status' => 'success'
                    ]],
                    'uniqueId' => $uniqueIdResult['uniqueId']
                ];
            }

            // Si erreur dans la vérification de l'ID unique
            if ($uniqueIdResult['status'] === 'error') {
                return [
                    ...$uniqueIdResult,
                    'currentStep' => 'Vérification des articles',
                    'progress' => 100,
                    'messages' => [[
                        'type' => 'error',
                        'message' => $uniqueIdResult['messages'][0]['message'] ?? 'Erreur lors de la vérification',
                        'title' => 'Contrôle du numéro de série'
                    ]]
                ];
            }

            // Vérification BTR
            try {
                $btrNumber = "BTR" . $pcdnum;
                $checkExistBtr = $this->pieceDetailsRepository->findByBtrNumber($btrNumber);

                if ($checkExistBtr) {
                    $this->executionLogger->addStep(
                        description: 'Vérification BTR',
                        status: 'info',
                        message: "BTR existant trouvé: $btrNumber"
                    );

                    $deleteResult = $this->automateService->processDeleteAutomate("BTR$pcdnum");
                    $this->executionLogger->logApiCall(
                        apiName: 'Automate',
                        endpoint: 'processDeleteAutomate',
                        request: ['pcdnum' => $pcdnum],
                        response: $deleteResult,
                        error: isset($deleteResult['status']) && $deleteResult['status'] === 'error' ? $deleteResult['message'] ?? null : null
                    );

                    if (isset($deleteResult['status']) && $deleteResult['status'] === 'error') {
                        return [
                            ...$deleteResult,
                            'currentStep' => 'Nettoyage BTR',
                            'progress' => 20
                        ];
                    }
                } else {
                    $this->timer->logStep(
                        "Vérification BTR",
                        'info',
                        'info',
                        "Aucun BTR existant à nettoyer"
                    );
                }
            } catch (\Exception $e) {
                $this->executionLogger->logError(
                    description: 'Erreur nettoyage BTR',
                    message: 'Le nettoyage des BTR existants a échoué',
                    user: $user,
                    error: $e,
                );
            }

            // Vérification des articles
            $articleCheckStartTime = microtime(true);
            $envelope = $this->commandBus->dispatch(new CheckArticlesCommand($pieceDetails));
            $articleCheckResult = $envelope->last(HandledStamp::class)->getResult();
            
            $this->executionLogger->addStep(
                description: 'Vérification des articles',
                status: $articleCheckResult['status'],
                message: json_encode($articleCheckResult),
                stepStartTime: $articleCheckStartTime
            );

            if ($articleCheckResult['status'] === 'error') {
                $this->executionLogger->finish(
                    description: 'Erreur vérification articles',
                    message: json_encode($articleCheckResult['messages']),
                    stepStatus: 1,
                    user: $user,
                    status: 'error'
                );
                return [
                    'status' => 'error',
                    'messages' => $articleCheckResult['messages'],
                    'details' => $articleCheckResult['details'],
                    'pcdnum' => $pcdnum
                ];
            }

            $envelope = $this->commandBus->dispatch(new CheckAffaireCommand($pcdid));
            $affaireResult = $envelope->last(HandledStamp::class)->getResult();

            if ($affaireResult['status'] === 'error') {
                return [
                    ...$articleCheckResult,
                    'status' => 'error',
                    'messages' => $affaireResult['messages']
                ];
            }

            $envelope = $this->commandBus->dispatch(new ProcessArticlesCommand($pieceDetails));
            $processResult = $envelope->last(HandledStamp::class)->getResult();

            $envelope = $this->commandBus->dispatch(new ProcessTransfertCommand($processResult, $affaireResult));
            $transfertResult = $envelope->last(HandledStamp::class)->getResult();

            $hasErrors = false;
            $errorMessages = [];

            foreach ($transfertResult as $message) {
                if ($message['status'] === 'error') {
                    $hasErrors = true;
                    $this->timer->logStep(
                        "Erreur lors du transfert",
                        'error',
                        'error',
                        $message['content']
                    );

                    $showMailto = str_contains($message['content'], 'informatique@latitudegps.com');

                    $errorMessages[] = [
                        'type' => 'error',
                        'message' => $message['content'],
                        'title' => $message['title'] ?? 'Vérification',
                        'content' => $message['content'],
                        'currentStep' => 'Traitement du transfert',
                        'progress' => 100,
                        'showMailto' => $showMailto
                    ];
                }
            }

            if ($hasErrors) {
                $this->timer->logStep(
                    "Validation terminée avec erreurs",
                    'error',
                    'error',
                    "Des erreurs ont été détectées"
                );

                $this->commandBus->dispatch(new UpdateMemoAndApiCommand(
                    $pcdid,
                    $memoId,
                    $user,
                    [[
                        'title' => 'Validation',
                        'content' => "Des erreurs ont été détectées",
                        'status' => 'error'
                    ]],
                    [
                        'status' => 'error',
                        'message' => "Des erreurs ont été détectées"
                    ]
                ));
                return [
                    'status' => 'error',
                    'currentStep' => 'Validation terminée',
                    'progress' => 100,
                    'messages' => $errorMessages,
                    'details' => $articleCheckResult['details'] ?? []
                ];
            }

            $this->timer->logStep(
                "Validation terminée",
                'success',
                'success',
                "Toutes les vérifications sont OK"
            );

            $this->commandBus->dispatch(new UpdateMemoAndApiCommand(
                $pcdid,
                $memoId,
                $user,
                [[
                    'title' => 'Validation',
                    'content' => "Validation de l'ODF terminée avec succès",
                    'status' => 'success'
                ]],
                [
                    'status' => 'success',
                    'message' => "Validation de l'ODF terminée avec succès"
                ]
            ));

            $this->executionLogger->finishHandler('ValidateOrderCommandHandler', $handlerStartTime, [
                'status' => 'success',
                'currentStep' => 'Validation terminée',
                'progress' => 100
            ]);

            return [
                'status' => 'success',
                'currentStep' => 'Validation terminée',
                'progress' => 100,
                'messages' => [[
                    'type' => 'success',
                    'message' => 'Validation de l\'ODF, vous pouvez lancer la commande',
                    'status' => 'success'
                ]],
                'details' => $articleCheckResult['details'] ?? []
            ];
        } catch (\Exception $e) {
            $this->executionLogger->logError(
                description: 'Erreur fatale',
                message: 'Erreur lors de la validation',
                user: $user,
                error: $e
            );

            return [
                'status' => 'error',
                'currentStep' => 'Validation échouée',
                'progress' => 100,
                'messages' => [[
                    'type' => 'error',
                    'message' => $e->getMessage(),
                    'status' => 'error'
                ]]
            ];
        }
    }
} 
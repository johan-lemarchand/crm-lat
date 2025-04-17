<?php

namespace App\Controller;

use App\ODF\Application\Command\ValidateOrder\ValidateOrderCommand;
use App\ODF\Domain\Repository\OdfLogRepositoryInterface;
use App\ODF\Domain\Repository\OdfExecutionRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use App\ODF\Application\Command\CreateOrder\CreateOrderCommand;
use Psr\Log\LoggerInterface;
use App\ODF\Application\Command\GetOrder\GetOrderCommand;
use App\ODF\Application\Command\ProcessPasscodes\ProcessPasscodesCommand;
use App\ODF\Application\Command\CreateManufacturingOrder\CreateManufacturingOrderCommand;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;
use App\ODF\Infrastructure\Service\OdfLogStatsService;
use App\Service\FormatService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OdfController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly OdfExecutionLogger $executionLogger,
        private readonly OdfLogRepositoryInterface $odfLogRepository,
        private readonly OdfExecutionRepositoryInterface $odfExecutionRepository,
        private readonly OdfLogStatsService $odfLogStatsService,
        private readonly FormatService $formatService,
        private readonly ParameterBagInterface $params
    ) {}

    #[Route('/api/odf/check', name: 'api_odf_check', methods: ['GET'])]
    public function check(Request $request): JsonResponse
    {
        $pcdid = $request->query->get('pcdid');
        $pcdnum = $request->query->get('pcdnum');
        $user = $request->query->get('user');
        
        // Test direct d'authentification Trimble
        try {
            // Appeler directement l'API Trimble pour tester l'authentification
            $url = $this->params->get('trimble.token_url');
            $client_id = $this->params->get('trimble.client_id');
            $client_secret = $this->params->get('trimble.client_secret');
            
            // Logger les paramètres (masquer une partie du secret pour la sécurité)
            $this->logger->info('Test authentification Trimble', [
                'url' => $url,
                'client_id' => $client_id,
                'client_secret' => substr($client_secret, 0, 4) . '...' . substr($client_secret, -4)
            ]);
            
            if (!$url || !$client_id || !$client_secret) {
                $this->logger->critical('Configuration Trimble incomplète', [
                    'url' => $url ? 'OK' : 'Manquant',
                    'client_id' => $client_id ? 'OK' : 'Manquant',
                    'client_secret' => $client_secret ? 'OK' : 'Manquant'
                ]);
            }
            
            $client = new \GuzzleHttp\Client();
            $response = $client->post($url, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'scope' => 'tapstoreapis'
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($result['access_token'])) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Erreur d\'authentification Trimble: Token non trouvé dans la réponse',
                    'sessionId' => uniqid('odf_session_', true),
                    'authenticationError' => true,
                    'messages' => [[
                        'type' => 'error',
                        'message' => 'Erreur d\'authentification Trimble: Token non trouvé dans la réponse',
                        'status' => 'error'
                    ]]
                ], 401);
            }
        } catch (\Exception $e) {
            // Vérifier si c'est une erreur 401 d'authentification
            $message = $e->getMessage();
            $is401 = str_contains($message, '401') ||
                str_contains($message, 'invalid_client');
            
            if ($is401) {
                $this->logger->critical('Erreur d\'authentification Trimble', [
                    'message' => $message,
                    'class' => get_class($e)
                ]);
                
                return $this->json([
                    'status' => 'error',
                    'message' => 'Erreur d\'authentification avec l\'API Trimble. Vérifiez les identifiants API.',
                    'detail' => $message,
                    'sessionId' => uniqid('odf_session_', true),
                    'authenticationError' => true,
                    'messages' => [[
                        'type' => 'error',
                        'message' => 'Erreur d\'authentification avec l\'API Trimble. Vérifiez les identifiants API.',
                        'status' => 'error'
                    ]]
                ], 401);
            }
            
            // Pour les autres erreurs, les logger mais continuer
            $this->logger->warning('Erreur lors du test d\'authentification Trimble', [
                'message' => $message,
                'class' => get_class($e)
            ]);
        }
        
        // Générer un sessionId unique pour cette tentative
        $sessionId = $request->query->get('sessionId');
        if (!$sessionId) {
            $sessionId = uniqid('odf_session_', true);
        }
        
        // Récupérer ou créer le log ODF et définir le sessionId
        $odfLog = $this->odfLogRepository->findOrCreate($pcdnum);
        
        // Si c'est une nouvelle session (pas de sessionId fourni), réinitialiser le compteur d'erreurs
        if (!$request->query->has('sessionId')) {
            $odfLog->setSessionId($sessionId);
            $odfLog->setErrorCount(0);
            $odfLog->setErrorsByStep([]);
            $this->odfLogRepository->save($odfLog, true);
        }
        $this->executionLogger->startLogging(
            controller: 'OdfController::check',
            description: "Vérification $pcdnum",
            pcdid: (int)$pcdid,
            pcdnum: $pcdnum,
            stepStatus: 1
        );

        if (!$pcdid) {
            $this->executionLogger->logError(
                description: 'Validation des paramètres',
                message: 'Paramètre pcdid manquant',
                user: $user,
                stepStatus: 1

            );

            return $this->json([
                'status' => 'error',
                'messages' => [[
                    'message' => 'Paramètre pcdid manquant',
                    'status' => 'error'
                ]]
            ], 400);
        }

        try {
            $commandStartTime = microtime(true);
            $command = new ValidateOrderCommand(
                pcdid: (int)$pcdid,
                pcdnum: $pcdnum ?? '',
                user: $user
            );

            $envelope = $this->commandBus->dispatch($command);
            
            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();

            $this->executionLogger->addStep(
                description: 'Exécution ValidateOrderCommand',
                status: $result['status'] ?? 'error',
                message: json_encode($result),
                stepStartTime: $commandStartTime,
                stepStatus: 1
            );
            if ($result['status'] === 'error') {
                $this->executionLogger->logError(
                    description: 'Sortie du controller',
                    message: $result['messages'][0]['message'] ?? 'Erreur lors de la validation',
                    user: $user,
                    stepStatus: 1
                );
                return $this->json([
                    'status' => 'error',
                    'messages' => $result['messages'],
                    'details' => $result['details'] ?? [],
                    'sessionId' => $sessionId
                ]);
            }

            $this->executionLogger->finish(
                description: 'Sortie du controller',
                message: 'Vérification terminée avec succès',
                stepStatus: 1,
                user: $user,
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Vérification réussie',
                'details' => $result,
                'sessionId' => $sessionId
            ]);
        } catch (HandlerFailedException | ExceptionInterface $e) {
            $this->executionLogger->logError(
                description: 'Erreur dans le controller',
                message: 'Erreur lors de la vérification',
                user: $user,
                error: $e,
                stepStatus: 1
            );
            
            // Incrémenter le compteur d'erreurs
            $odfLog = $this->odfLogRepository->findOneBy(['name' => $pcdnum]);
            if ($odfLog) {
                $odfLog->incrementError('check');
                $this->odfLogRepository->save($odfLog, true);
            }
            
            $previous = $e->getPrevious();
            $message = $previous ? $previous->getMessage() : $e->getMessage();
            
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la vérification: ' . $message,
                'sessionId' => $sessionId
            ], 500);
        } catch (\Exception $e) {
            $this->executionLogger->logError(
                description: 'Erreur dans le controller',
                message: 'Erreur lors de la vérification',
                user: $user,
                error: $e,
                stepStatus: 1
            );
            
            // Incrémenter le compteur d'erreurs
            $odfLog = $this->odfLogRepository->findOneBy(['name' => $pcdnum]);
            if ($odfLog) {
                $odfLog->incrementError('check');
                $this->odfLogRepository->save($odfLog, true);
            }
            
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la vérification: ' . $e->getMessage(),
                'sessionId' => $sessionId
            ], 500);
        }
    }

    #[Route('/api/odf/create-order', name: 'app_odf_create_order', methods: ['GET'])]
    public function createOrder(Request $request): JsonResponse
    {
        $pcdid = $request->query->get('pcdid');
        $pcdnum = $request->query->get('pcdnum');
        $user = $request->query->get('user');
        $sessionId = $request->query->get('sessionId');
        
        // Si pas de sessionId, on en crée un nouveau
        if (!$sessionId) {
            $sessionId = uniqid('odf_session_', true);
        }
        
        $this->executionLogger->startLogging(
            controller: 'OdfController::createOrder',
            description: "Création commande $pcdnum",
            pcdid: (int)$pcdid,
            pcdnum: $pcdnum,
            stepStatus: 2
        );

        try {
            $command = new CreateOrderCommand(
                pcdid: (int)$pcdid,
                user: $user,
                pcdnum: $pcdnum
            );

            $envelope = $this->commandBus->dispatch($command);

            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();
            
            // Vérifier si le résultat contient une erreur
            if (isset($result['status']) && $result['status'] === 'error') {
                return $this->json([
                    'status' => 'error',
                    'message' => $result['messages'][0]['message'] ?? 'Erreur lors de la création de la commande',
                    'details' => $result,
                    'sessionId' => $sessionId
                ]);
            }

            $this->executionLogger->addStep(
                description: 'Création commande réussie',
                status: 'success',
                message: 'Commande créée avec succès',
                stepStatus: 2
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Commande créée avec succès',
                'details' => $result,
                'sessionId' => $sessionId
            ]);
        } catch (HandlerFailedException $e) {
            $this->executionLogger->logError(
                description: 'Erreur dans le controller',
                message: 'Erreur lors de la création de la commande',
                user: $user,
                error: $e,
                stepStatus: 2
            );
            
            // Incrémenter le compteur d'erreurs
            $odfLog = $this->odfLogRepository->findOneBy(['name' => $pcdnum]);
            if ($odfLog) {
                $odfLog->incrementError('createOrder');
                $this->odfLogRepository->save($odfLog, true);
            }
            
            $previous = $e->getPrevious();
            $message = $previous ? $previous->getMessage() : $e->getMessage();
            
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création de la commande: ' . $message,
                'sessionId' => $sessionId
            ], 500);
        } catch (\Exception | ExceptionInterface $e) {
            $this->executionLogger->logError(
                description: 'Erreur dans le controller',
                message: 'Erreur lors de la création de la commande',
                user: $user,
                error: $e,
                stepStatus: 2
            );
            
            // Incrémenter le compteur d'erreurs
            $odfLog = $this->odfLogRepository->findOneBy(['name' => $pcdnum]);
            if ($odfLog) {
                $odfLog->incrementError('createOrder');
                $this->odfLogRepository->save($odfLog, true);
            }
            
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création de la commande: ' . $e->getMessage(),
                'sessionId' => $sessionId
            ], 500);
        }
    }

    #[Route('/api/odf/get-order', name: 'api_odf_get_order', methods: ['GET'])]
    public function getOrder(Request $request): JsonResponse
    {
        try {
            $pcdid = $request->query->get('pcdid');
            $user = $request->query->get('user');
            $pcdnum = $request->query->get('pcdnum');

            $this->executionLogger->startLogging(
                controller: 'OdfController::getOrder',
                description: "Récupération $pcdnum",
                pcdid: (int)$pcdid,
                pcdnum: $pcdnum,
                stepStatus: 3
            );

            if (!$user || !$pcdid) {
                $this->executionLogger->logError(
                    description: 'Validation des paramètres',
                    message: 'Paramètres manquants',
                    user: $user,
                    stepStatus: 3
                );
                return $this->json([
                    'status' => 'error',
                    'messages' => [[
                        'message' => 'Paramètres manquants',
                        'status' => 'error'
                    ]]
                ], 400);
            }

            $this->executionLogger->addStep(
                description: 'Validation des paramètres',
                status: 'success',
                message: 'Paramètres valides',
                stepStatus: 3
            );

            $commandStartTime = microtime(true);
            $command = new GetOrderCommand(
                pcdid: (int)$pcdid,
                user: $user
            );

            $envelope = $this->commandBus->dispatch($command);
            
            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();

            // Vérifier si le résultat est de type "retry"
            $isRetry = is_array($result) && isset($result['type']) && $result['type'] === 'retry';
            
            $this->executionLogger->addStep(
                description: 'Exécution GetOrderCommand',
                status: $isRetry ? 'info' : ($result ? 'success' : 'error'),
                message: $isRetry 
                    ? ($result['message'] ?? 'En attente de la récupération de la commande') 
                    : ($result ? 'Commande récupérée avec succès' : 'Erreur lors de la récupération'),
                stepStartTime: $commandStartTime,
                stepStatus: 3
            );

            if (!$result) {
                $this->executionLogger->logError(
                    description: 'Sortie du controller',
                    message: 'Erreur lors de la récupération de la commande',
                    user: $user,
                    stepStatus: 3
                );
                return $this->json([
                    'status' => 'error',
                    'messages' => [[
                        'message' => 'Erreur lors de la récupération de la commande',
                        'status' => 'error'
                    ]]
                ]);
            }
            
            // Si c'est un retry, ne pas appeler finish() pour ne pas terminer le log
            if (!$isRetry) {
                $this->executionLogger->finish(
                    description: 'Sortie du controller',
                    message: 'Récupération terminée avec succès',
                    stepStatus: 3,
                    user: $user,
                );
            } else {
                // Pour un retry, utiliser la méthode logRetry
                $this->executionLogger->logRetry(
                    description: 'Sortie du controller (tentative en cours)',
                    message: $result['message'] ?? 'En attente de la récupération de la commande',
                    currentTry: $result['retryCount'] ?? 1,
                    maxRetries: $result['maxRetries'] ?? 3,
                    stepStatus: 3
                );
            }

            return $this->json($result);

        } catch (HandlerFailedException | ExceptionInterface $e) {
            $previous = $e->getPrevious() ?? $e;
            
            $this->executionLogger->logError(
                description: 'Erreur dans le controller',
                message: 'Erreur lors de la récupération de la commande',
                user: $user,
                error: $previous,
                stepStatus: 3
            );

            return $this->json([
                'status' => 'error',
                'messages' => [[
                    'message' => 'Erreur lors de la récupération de la commande : ' . $previous->getMessage(),
                    'status' => 'error'
                ]]
            ]);
        }
    }

    #[Route('/api/odf/get-activation', name: 'api_odf_get_activation', methods: ['GET'])]
    public function getActivation(Request $request): JsonResponse
    {
        try {
            $pcdid = $request->query->get('pcdid');
            $user = $request->query->get('user');
            $orderNumber = $request->query->get('orderNumber');
            $pcdnum = $request->query->get('pcdnum');
            
            $this->executionLogger->startLogging(
                controller: 'OdfController::getActivation',
                description: "Récupération activation $pcdnum",
                pcdid: (int)$pcdid,
                pcdnum: $pcdnum,
                stepStatus: 4
            );

            if (!$user || !$pcdid || !$orderNumber) {
                $this->executionLogger->logError(
                    description: 'Validation des paramètres',
                    message: 'Paramètres manquants (pcdid, user ou orderNumber)',
                    user: $user,
                    stepStatus: 4
                );
                return $this->json([
                    'status' => 'error',
                    'messages' => [[
                        'message' => 'Paramètres manquants (pcdid, user ou orderNumber)',
                        'status' => 'error'
                    ]]
                ], 400);
            }

            $this->executionLogger->addStep(
                description: 'Validation des paramètres',
                status: 'success',
                message: 'Paramètres valides',
                stepStatus: 4
            );

            $commandStartTime = microtime(true);
            $command = new ProcessPasscodesCommand(
                pcdid: (int)$pcdid,
                user: $user,
                orderNumber: $orderNumber,
                pcdnum: $pcdnum
            );

            $envelope = $this->commandBus->dispatch($command);
            
            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();

            // Vérifier si le résultat est de type "retry"
            $isRetry = is_array($result) && isset($result['type']) && $result['type'] === 'retry';

            $this->executionLogger->addStep(
                description: 'Exécution ProcessPasscodesCommand',
                status: $isRetry ? 'info' : ($result ? 'success' : 'error'),
                message: $isRetry 
                    ? ($result['message'] ?? 'En attente de la récupération des informations d\'activation') 
                    : ($result ? 'Activation récupérée avec succès' : 'Erreur lors de la récupération'),
                stepStartTime: $commandStartTime,
                stepStatus: 4
            );

            if (!$result) {
                $this->executionLogger->logError(
                    description: 'Sortie du controller',
                    message: 'Erreur lors de la récupération des informations d\'activation',
                    user: $user,
                    stepStatus: 4
                );
                return $this->json([
                    'status' => 'error',
                    'messages' => [[
                        'message' => 'Erreur lors de la récupération des informations d\'activation',
                        'status' => 'error'
                    ]]
                ]);
            }
            
            // Si c'est un retry, ne pas appeler finish() pour ne pas terminer le log
            if (!$isRetry) {
                $this->executionLogger->finish(
                    description: 'Sortie du controller',
                    message: 'Récupération activation terminée avec succès',
                    stepStatus: 4,
                    user: $user,
                );
            } else {
                // Pour un retry, utiliser la méthode logRetry
                $this->executionLogger->logRetry(
                    description: 'Sortie du controller (tentative en cours)',
                    message: $result['message'] ?? 'En attente de la récupération des informations d\'activation',
                    currentTry: $result['retryCount'] ?? 1,
                    maxRetries: $result['maxRetries'] ?? 3,
                    stepStatus: 4
                );
            }

            return $this->json($result);

        } catch (HandlerFailedException | ExceptionInterface $e) {
            $previous = $e->getPrevious() ?? $e;
            
            $this->executionLogger->logError(
                description: 'Erreur dans le controller',
                message: 'Erreur lors de la récupération des informations d\'activation',
                user: $user,
                error: $previous
            );

            return $this->json([
                'status' => 'error',
                'messages' => [[
                    'message' => 'Erreur lors de la récupération des informations d\'activation : ' . $previous->getMessage(),
                    'status' => 'error'
                ]]
            ]);
        }
    }
    
    #[Route('/api/odf/create-bdf', name: 'api_odf_create_bdf', methods: ['POST'])]
    public function createBdf(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $pcdid = (int) ($data['pcdid'] ?? 0);
            $pcdnum = $data['pcdnum'] ?? '';
            $orderNumber = $data['orderNumber'] ?? '';
            $user = $data['user'] ?? 'anonymous';
            $activationResult = $data['activationResult'] ?? [];

            $this->executionLogger->startLogging(
                controller: 'OdfController::createBdf',
                description: "Création BDF pour $pcdnum",
                pcdid: $pcdid,
                pcdnum: $pcdnum,
                stepStatus: 5
            );

            if (!$pcdid || !$orderNumber) {
                $this->executionLogger->logError(
                    description: 'Validation des paramètres',
                    message: 'Données manquantes pour la création du BDF',
                    user: $user,
                    stepStatus: 5
                );
                return $this->json([
                    'status' => 'error',
                    'message' => 'Données manquantes pour la création du BDF',
                    'messages' => [
                        [
                            'type' => 'error',
                            'message' => 'Données manquantes pour la création du BDF',
                            'status' => 'error'
                        ]
                    ]
                ], 400);
            }

            $this->executionLogger->addStep(
                description: 'Validation des paramètres',
                status: 'success',
                message: 'Paramètres valides',
                stepStatus: 5
            );

            $commandStartTime = microtime(true);
            $command = new CreateManufacturingOrderCommand(
                $pcdid,
                $orderNumber,
                (array)$activationResult,
                $user
            );

            $envelope = $this->commandBus->dispatch($command);
            
            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();

            $this->executionLogger->addStep(
                description: 'Exécution CreateManufacturingOrderCommand',
                status: $result ? 'success' : 'error',
                message: $result ? 'BDF créé avec succès' : 'Erreur lors de la création',
                stepStartTime: $commandStartTime,
                stepStatus: 5
            );

            if (!$result) {
                $this->executionLogger->logError(
                    description: 'Sortie du controller',
                    message: 'Erreur lors de la création du bon de fabrication',
                    user: $user,
                    stepStatus: 5
                );
                return $this->json([
                    'status' => 'error',
                    'messages' => [[
                        'message' => 'Erreur lors de la création du bon de fabrication',
                        'status' => 'error'
                    ]]
                ]);
            }

            $this->executionLogger->finish(
                description: 'Sortie du controller',
                message: 'Création BDF terminée avec succès',
                stepStatus: 5,
                user: $user,
            );

            return $this->json($result);

        } catch (\Exception | ExceptionInterface $e) {
            $this->executionLogger->logError(
                description: 'Erreur dans le controller',
                message: 'Erreur lors de la création du bon de fabrication',
                user: $user,
                error: $e,
                stepStatus: 5
            );
            
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création du bon de fabrication: ' . $e->getMessage(),
                'messages' => [
                    [
                        'type' => 'error',
                        'message' => 'Erreur lors de la création du bon de fabrication: ' . $e->getMessage(),
                        'status' => 'error'
                    ]
                ]
            ], 500);
        }
    }

    #[Route('/api/odf/save-execution-time', name: 'api_odf_save_execution_time', methods: ['POST'])]
    public function saveExecutionTime(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['pcdnum']) || !isset($data['executionTime']) || !isset($data['executionTimePause'])) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Données manquantes pour l\'enregistrement des temps d\'exécution'
                ], 400);
            }
            
            $pcdnum = $data['pcdnum'];
            $user = $data['user'] ?? 'anonymous';
            $executionTime = (int) $data['executionTime'];
            $executionTimePause = (int) $data['executionTimePause'];
            
            // Démarrer la journalisation APRÈS avoir récupéré le pcdnum
            $this->executionLogger->startLogging(
                controller: 'OdfController::saveExecutionTime',
                description: "Finalisation",
                pcdid: null,
                pcdnum: $pcdnum, // Utiliser le pcdnum récupéré
                stepStatus: 6
            );
            
            $this->executionLogger->addStep(
                description: 'Réception des données',
                status: 'success',
                message: 'Données reçues pour enregistrement du temps',
                stepStatus: 6
            );
            
            $odfLog = $this->odfLogRepository->findOneBy(['name' => $pcdnum]);

            if (!$odfLog) {
                $this->executionLogger->logError(
                    description: 'Recherche du log ODF',
                    message: 'Aucun log ODF trouvé pour le pcdnum: ' . $pcdnum,
                    user: $user,
                    stepStatus: 6
                );
                return $this->json([
                    'status' => 'error',
                    'message' => 'Aucun log ODF trouvé pour ce pcdnum'
                ], 404);
            }
            
            $this->executionLogger->addStep(
                description: 'Mise à jour du log ODF',
                status: 'success',
                message: 'Mise à jour des temps d\'exécution: ' . $executionTime . 'ms, pause: ' . $executionTimePause . 'ms',
                stepStatus: 6
            );
            
            $odfLog->setExecutionTime($executionTime);
            $odfLog->setExecutionTimePause($executionTimePause);
            $odfLog->setStatus('finish');
            
            // Enregistrer les modifications
            $this->odfLogRepository->save($odfLog, true);
            
            // Appeler finish() après l'enregistrement des modifications
            $this->executionLogger->finish(
                description: 'Sortie du controller',
                message: 'Commande terminée avec succès, temps enregistré: ' . $executionTime . ' et temps de pause enregistré: ' . $executionTimePause,
                stepStatus: 6,
                user: $user,
            );
            
            return $this->json([
                'status' => 'success',
                'message' => 'Temps d\'exécution enregistrés avec succès'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'enregistrement des temps d\'exécution', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Si nous avons déjà commencé à logger, enregistrer l'erreur
            if (isset($pcdnum)) {
                $this->executionLogger->logError(
                    description: 'Erreur',
                    message: 'Erreur lors de l\'enregistrement des temps d\'exécution : ' . $e->getMessage(),
                    user: $user,
                    stepStatus: 6
                );
            }
            
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'enregistrement des temps d\'exécution : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/odf/logs', name: 'api_odf_logs', methods: ['GET'])]
    public function getOdfLogs(): JsonResponse
    {
        try {
            // Utiliser le service pour récupérer les statistiques des logs ODF
            $result = $this->odfLogStatsService->getAllOdfLogsStats();
            
            return $this->json([
                'status' => 'success',
                'logs' => $result['logs'],
                'totalSize' => $result['totalSize']
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des logs ODF: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/odf/logs/all', name: 'api_odf_logs_all', methods: ['GET'])]
    public function getAllOdfLogs(): JsonResponse
    {
        try {
            // Utiliser le service pour récupérer tous les logs ODF sans limite
            $result = $this->odfLogStatsService->getAllOdfLogsStatsWithoutLimit();
            
            return $this->json([
                'status' => 'success',
                'logs' => $result['logs'],
                'totalSize' => $result['totalSize']
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des logs ODF: ' . $e->getMessage()
            ], 500);
        }
    }
}

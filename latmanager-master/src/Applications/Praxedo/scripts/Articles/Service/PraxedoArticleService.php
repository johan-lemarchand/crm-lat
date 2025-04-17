<?php

namespace App\Applications\Praxedo\scripts\Articles\Service;

use App\Applications\Praxedo\Common\PraxedoClient;
use App\Applications\Praxedo\Common\PraxedoException;
use Psr\Log\LoggerInterface;
use App\Applications\Praxedo\Common\PraxedoApiLoggerInterface;
use SoapFault;

class PraxedoArticleService
{
    private PraxedoClient $client;

    private const MAX_ITEMS_PER_CALL = 100;

    private ?string $executionId = null;

    /**
     * @throws SoapFault
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PraxedoApiLoggerInterface $praxedoApiLogger
    ) {
        $baseUrl = $_ENV['PRAXEDO_BASE_URL'];
        $this->client = new PraxedoClient(
            sprintf('%s/ItemManager?wsdl', $baseUrl),
            $_ENV['PRAXEDO_LOGIN'],
            $_ENV['PRAXEDO_PASSWORD'],
            $this->logger,
            []
        );
    }

    public function setExecutionId(string $executionId): void
    {
        $this->executionId = $executionId;
    }

    /**
     * Récupère la liste des articles depuis Praxedo.
     *
     * @param array|null $itemIds Liste des IDs d'articles à récupérer
     * @return array
     */
    public function getArticles(?array $itemIds = null): array
    {
        try {
            if (empty($itemIds)) {
                return [];
            }

            $itemIds = array_map('strval', $itemIds);

            $params = new \stdClass();
            $params->requestedItems = $itemIds;
            $params->options = [];

            $startTime = microtime(true);
            $result = $this->client->call('getItems', $params);
            $duration = microtime(true) - $startTime;

            $responseCode = $result->return->resultCode ?? null;
            $responseMessage = $result->return->message ?? 'Message inconnu';

            if (!$result || !isset($result->return)) {
                $this->logger->error('Réponse Praxedo invalide', [
                    'result' => $result ? print_r($result, true) : 'null',
                    'response_code' => $responseCode
                ]);

                if ($this->executionId !== null) {
                    $this->praxedoApiLogger->logApiCall(
                        $this->executionId,
                        '/ItemManager/getItems',
                        'POST',
                        json_encode($params),
                        json_encode(['error' => 'Réponse invalide']),
                        $responseCode ?? 500,
                        $duration
                    );
                }

                return [];
            }

            if (isset($result->return->resultCode) && 0 !== $result->return->resultCode) {
                $errorMessage = $this->getErrorMessage($result->return->resultCode);
                $this->logger->error('Erreur Praxedo', [
                    'code' => $responseCode,
                    'message' => $responseMessage,
                    'error_message' => $errorMessage,
                    'first_five_ids' => array_slice($itemIds, 0, 5)
                ]);

                if ($this->executionId !== null) {
                    $this->praxedoApiLogger->logApiCall(
                        $this->executionId,
                        '/ItemManager/getItems',
                        'POST',
                        json_encode($params),
                        json_encode([
                            'error' => $errorMessage,
                            'message' => $responseMessage,
                            'code' => $responseCode
                        ], JSON_PRETTY_PRINT),
                        $responseCode,
                        $duration
                    );
                }

                return [];
            }

            $articles = $this->formatArticles($result->return->entities ?? []);

            if ($this->executionId !== null) {
                $this->praxedoApiLogger->logApiCall(
                    $this->executionId,
                    '/ItemManager/getItems',
                    'POST',
                    json_encode($params),
                    json_encode([
                        'success' => true,
                        'articles_count' => count($articles),
                        'code' => $responseCode
                    ], JSON_PRETTY_PRINT),
                    $responseCode ?? 200,
                    $duration
                );
            }

            return $articles;
        } catch (PraxedoException $e) {
            $this->logger->error('Erreur lors de la récupération des articles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function getErrorMessage(int $code): string
    {
        return match ($code) {
            0 => 'Succès',
            1 => 'Une erreur interne Praxedo s\'est produite',
            4 => 'Une des options fournies n\'est pas définie pour la méthode',
            50 => 'L\'article demandé n\'existe pas dans Praxedo',
            51 => 'Trop d\'articles demandés (maximum 100)',
            default => 'Erreur inconnue',
        };
    }
    private function formatArticles($entities): array
    {
        if (!$entities) {
            return [];
        }

        if (!is_array($entities)) {
            $entities = [$entities];
        }

        $articles = [];
        foreach ($entities as $entity) {
            $articles[] = [
                'id' => $entity->id ?? '',
                'name' => $entity->name ?? '',
                'itemCategoryId' => $entity->itemCategoryId ?? '',
                'description' => $entity->description ?? '',
                'unitPrice' => $entity->billingParameters->unitPrice ?? '',
                'vatRates' => $entity->billingParameters->vatRates ?? [],
                'dimension' => $entity->dimension ?? '',
                'reference' => $entity->reference ?? '',
                'barCode' => $entity->barCode ?? '',
                'manufacturer' => $entity->manufacturer ?? '',
                'fixedPrice' => $entity->fixedPrice ?? false,
                'serialNumberRegex' => $entity->serialNumberRegex ?? '',
            ];
        }

        return $articles;
    }

    /**
     * Met à jour les prix des articles dans Praxedo.
     *
     * @param array $articles Liste des articles à mettre à jour
     *                        [
     *                        [
     *                        'id' => string,
     *                        'name' => string,
     *                        'categoryId' => string,
     *                        'reference' => string,
     *                        'unitPrice' => float
     *                        ],
     *                        ...
     *                        ]
     *
     * @throws PraxedoException
     */
    public function updateItemsPrices(array $articles): array
    {
        $this->logger->info('Mise à jour des prix des articles', [
            'nombre_articles' => count($articles),
        ]);

        $results = [];
        try {
            $chunks = array_chunk($articles, self::MAX_ITEMS_PER_CALL);

            foreach ($chunks as $chunk) {
                $params = new \stdClass();
                $params->items = [];

                foreach ($chunk as $article) {
                    $item = new \stdClass();
                    $item->id = $article['id'];
                    $item->name = $article['name'];
                    $item->itemCategoryId = $article['categoryId'];
                    $item->reference = $article['reference'];
                    $item->billingParameters = new \stdClass();
                    $item->billingParameters->unitPrice = $article['unitPrice'];
                    $item->billingParameters->vatRates = [0.2];

                    $params->items[] = $item;
                }
                $params->options = [];

                $startTime = microtime(true);
                $result = $this->client->call('createItems', $params);
                $duration = microtime(true) - $startTime;

                $responseCode = $result->return->resultCode ?? null;
                $responseMessage = $result->return->message ?? 'Pas de message';

                if (!$result || !isset($result->return)) {
                    $this->praxedoApiLogger->logApiCall(
                        $this->executionId,
                        '/ItemManager/',
                        'POST',
                        json_encode($params),
                        json_encode(['error' => 'Réponse invalide']),
                        500,
                        $duration
                    );
                    throw new PraxedoException('Erreur lors de la mise à jour des articles : réponse invalide');
                }

                if (0 !== $responseCode) {
                    $this->logger->error('Erreur globale lors de la mise à jour des articles', [
                        'code' => $responseCode,
                        'message' => $responseMessage,
                    ]);
                }

                $resultDetails = [];
                if (isset($result->return->results)) {
                    $resultItems = is_array($result->return->results) ? $result->return->results : [$result->return->results];
                    foreach ($resultItems as $resultItem) {
                        $itemResult = [
                            'reference' => $resultItem->id,
                            'code' => $resultItem->resultCode,
                            'message' => $resultItem->message ?? 'Pas de message',
                            'status' => 0 === $resultItem->resultCode ? 'success' : 'error',
                        ];
                        $results[] = $itemResult;
                        $resultDetails[] = $itemResult;
                    }
                }

                $this->praxedoApiLogger->logApiCall(
                    $this->executionId,
                    '/ItemManager/createItems',
                    'POST',
                    json_encode($params),
                    json_encode([
                        'global_code' => $responseCode,
                        'global_message' => $responseMessage,
                        'items_results' => $resultDetails
                    ], JSON_PRETTY_PRINT),
                    $responseCode ?? 200,
                    $duration
                );

                $this->logger->info('Lot d\'articles traité', [
                    'nombre_articles' => count($chunk),
                    'premier_article' => $chunk[0]['reference'],
                    'dernier_article' => end($chunk)['reference'],
                    'code_retour' => $responseCode
                ]);

                usleep(100000);
            }

            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour des prix des articles', [
                'error' => $e->getMessage(),
                'nombre_articles' => count($articles),
            ]);

            $this->praxedoApiLogger->logApiCall(
                $this->executionId,
                '/ItemManager/createItems',
                'POST',
                json_encode(['articles_count' => count($articles)]),
                json_encode(['error' => $e->getMessage()]),
                500,
                0
            );

            throw new PraxedoException('Erreur lors de la mise à jour des prix : '.$e->getMessage());
        }
    }
}

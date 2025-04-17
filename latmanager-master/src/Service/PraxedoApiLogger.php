<?php

namespace App\Service;

use App\Entity\CommandExecution;
use App\Entity\PraxedoApiLog;
use App\Applications\Praxedo\Common\PraxedoApiLoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class PraxedoApiLogger implements PraxedoApiLoggerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ORMException
     */
    public function logApiCall(
        string $execution,
        string $endpoint,
        string $method,
        string $request,
        string $response,
        int $statusCode,
        float $duration
    ): void {
        $executionEntity = $this->entityManager->getReference(CommandExecution::class, $execution);
        
        $apiLog = new PraxedoApiLog();
        $apiLog->setExecution($executionEntity);
        $apiLog->setEndpoint($endpoint);
        $apiLog->setMethod($method);
        $apiLog->setRequestXml($request);
        $apiLog->setResponseXml($response);
        $apiLog->setStatusCode($statusCode);
        $apiLog->setDuration($duration);

        $this->entityManager->persist($apiLog);
        $this->entityManager->flush();
    }
}

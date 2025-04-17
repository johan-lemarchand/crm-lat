<?php

namespace App\Applications\Praxedo\Common;

interface PraxedoApiLoggerInterface
{
    /**
     * Log un appel à l'API Praxedo
     *
     * @param string $execution Identifiant de l'exécution
     * @param string $endpoint Point d'entrée de l'API appelé
     * @param string $method Méthode HTTP utilisée
     * @param string $request Requête envoyée (JSON)
     * @param string $response Réponse reçue (JSON)
     * @param int $statusCode Code de statut HTTP
     * @param float $duration Durée de l'appel en secondes
     */
    public function logApiCall(
        string $execution,
        string $endpoint,
        string $method,
        string $request,
        string $response,
        int $statusCode,
        float $duration
    ): void;
} 
<?php

namespace App\ODF\Infrastructure\Service;

use App\ODF\Domain\Service\TransfertServiceInterface;
use App\ODF\Infrastructure\Service\Timer;

class TransfertService implements TransfertServiceInterface
{
    public function __construct(
        private readonly Timer $timer
    ) {}

    public function processTransfert(array $processResult, array $affaireResult): array
    {
        $messages = [];

        // Vérifier si le traitement des articles a réussi
        if (isset($processResult['errors']) && !empty($processResult['errors'])) {
            foreach ($processResult['errors'] as $error) {
                $messages[] = [
                    'status' => 'error',
                    'title' => 'Erreur de traitement',
                    'content' => $error['message']
                ];
            }
            return $messages;
        }

        // Vérifier si l'affaire existe
        if (empty($affaireResult)) {
            $messages[] = [
                'status' => 'error',
                'title' => 'Erreur affaire',
                'content' => "L'affaire n'existe pas ou n'a pas pu être récupérée"
            ];
            return $messages;
        }

        // Log du succès
        $this->timer->logStep(
            "Transfert réussi",
            'success',
            'success',
            "Le transfert des articles a été effectué avec succès"
        );

        $messages[] = [
            'status' => 'success',
            'title' => 'Transfert',
            'content' => 'Le transfert a été effectué avec succès'
        ];

        return $messages;
    }
}

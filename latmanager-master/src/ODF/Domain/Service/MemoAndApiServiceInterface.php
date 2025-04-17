<?php

namespace App\ODF\Domain\Service;

interface MemoAndApiServiceInterface
{
    /**
     * Met à jour un mémo avec des messages
     *
     * @param int $memoId ID du mémo
     * @param array $messages Messages à ajouter au mémo
     * @param string $user Utilisateur effectuant la mise à jour
     */
    public function updateMemo(int $memoId, array $messages, string $user): void;

    /**
     * Met à jour les informations d'API dans PIECEDIVERS
     *
     * @param int $pcdid ID de la pièce
     * @param array $data Données à mettre à jour
     */
    public function updatePieceDiversApi(int $pcdid, string $user, array $data): void;
}

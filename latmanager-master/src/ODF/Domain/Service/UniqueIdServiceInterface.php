<?php

namespace App\ODF\Domain\Service;

interface UniqueIdServiceInterface
{
    /**
     * Vérifie l'identifiant unique d'une pièce
     *
     * @param int $pcdid ID de la pièce
     * @return array|null Retourne un tableau avec les informations de l'ID unique ou null si non trouvé
     */
    public function checkCloseOdfAndUniqueId(int $pcdid): ?array;

    /**
     * Met à jour l'identifiant unique d'une pièce
     *
     * @param int $pcdid
     * @param string $uniqueId
     */
    public function updateUniqueId(int $pcdid, string $uniqueId): void;
}

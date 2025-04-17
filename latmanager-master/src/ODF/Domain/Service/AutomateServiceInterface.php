<?php

namespace App\ODF\Domain\Service;

interface AutomateServiceInterface
{
    /**
     * Traite la fabrication via l'automate
     *
     * @param array $items Articles et coupons
     * @param string $codeAffaire Code affaire
     * @param string $orderNumber Numéro de commande
     * @param string $user Utilisateur
     * @param string|null $memo Texte du mémo
     * @return bool Succès ou échec
     */
    public function processFabricationAutomate(array $items, string $codeAffaire, string $orderNumber, string $user, ?string $memo = null): bool;

    /**
     * Supprime une pièce via l'automate
     *
     * @param string $pcdnum Numéro de pièce
     * @return array Messages de résultat
     */
    public function processDeleteAutomate(string $pcdnum): array;

    /**
     * Clôture un ODF via l'automate
     *
     * @param string $odfNumber Numéro de l'ODF
     * @return bool Succès ou échec
     */
    public function processCloseODFAutomate(string $odfNumber): bool;

    public function deleteWslCodeInWSLOCK(string $pcdnum): void;

    public function processTransfertAutomate(array $processResult, array $affaireResult): array;

    public function createAbo(array $automateE, array $automateAA, array $automateAB, array $automateAE, array $automateAF, array $automateAL, array $lignes, int $memoId, string $user, string $pcvnum): array;
}
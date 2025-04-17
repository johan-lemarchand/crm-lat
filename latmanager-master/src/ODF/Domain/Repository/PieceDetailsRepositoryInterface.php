<?php

namespace App\ODF\Domain\Repository;

interface PieceDetailsRepositoryInterface
{
    public function findByPcdid(int $pcdid): array;
    public function findByBtrNumber(string $btrNumber): bool;
    public function exists(int $pcdid): bool;
    public function findUniqueIdByPcdid(int $pcdid): ?array;
    public function updateUniqueId(int $pcdid, string $uniqueId): void;
    public function getArticleParent(string $pcdnum): array;
    
    /**
     * Récupère un numéro de série pour un coupon
     * 
     * @param int $artId ID de l'article
     * @param string $pcdNum Numéro de pièce
     * @param array $usedSerialNumbers Numéros de série déjà utilisés
     * @return string|null Numéro de série ou null si non trouvé
     */
    public function getNumberSerieCoupon(int $artId, string $pcdNum, array $usedSerialNumbers = []): ?string;
    
    /**
     * Récupère le CUMP d'un coupon
     * 
     * @param int $artId ID de l'article
     * @param string $serialNumber Numéro de série
     * @return float|null CUMP du coupon ou null si non trouvé
     */
    public function getCouponCump(int $artId, string $serialNumber): ?float;
    
    /**
     * Met à jour le statut d'une pièce
     * 
     * @param int $pcdid ID de la pièce
     * @param string $status Nouveau statut
     * @return void
     */
    public function updatePieceStatus(int $pcdid, string $status): void;
    public function CheckLastEnterStockDate(string $numSerie, string $artId): array;
    
    /**
     * Sauvegarde un numéro de série et son modèle associé
     * 
     * @param string $serialNumber Numéro de série
     * @param string $model Modèle du fabricant
     * @return void
     */
    public function saveSerialNumberAndModel(string $serialNumber, string $model): void;
    public function getQuantityCheck(int $pcdid): array;
} 
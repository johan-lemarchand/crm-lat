<?php

namespace App\ODF\Domain\Service;

interface LockServiceInterface
{
    /**
     * Vérifie si une pièce est verrouillée
     *
     * @param int $pcdid ID de la pièce
     * @param string $pcdnum Numéro de la pièce
     * @return array{status: string, messages: array<array{message: string, status: string}>}
     */
    public function checkLock(int $pcdid, string $pcdnum): array;

    public function cleanExpiredLocks(): void;
    public function isSerialNumberLocked(string $serialNumber): bool;
    public function lockSerialNumbers(array $serialNumbers, string $odf, string $articleCode, string $proCodeParent): void;
    public function unlockSerialNumbers(array $coupons): void;
    public function cleanCouponLocks(string $odf, string $artCode): void;
    public function processLockCoupons(array $pieceDetail, int $qteTotal, array &$items): void;
}

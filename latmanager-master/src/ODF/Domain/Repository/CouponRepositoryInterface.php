<?php

namespace App\ODF\Domain\Repository;

interface CouponRepositoryInterface
{
    /**
     * @param int $artId
     * @param int $quantity
     * @param array $lockedSerials
     * @return array<array{
     *     OPEID: int,
     *     OPENUMSERIE: string,
     *     QTELIVRABLE: float,
     *     OPELASTPA: float,
     *     OPEPMP: float,
     *     OPECUMP: float
     * }>
     */
    public function findAvailableCoupons(int $artId, int $quantity, array $lockedSerials = []): array;
} 
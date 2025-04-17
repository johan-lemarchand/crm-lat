<?php

namespace App\ODF\Domain\Repository;

use App\ODF\Domain\Entity\Order;

interface OrderRepositoryInterface
{
    public function findByPcdId(int $pcdid): ?Order;
    public function updateOrderNumber(int $pcdid, string $orderNumber): void;
}

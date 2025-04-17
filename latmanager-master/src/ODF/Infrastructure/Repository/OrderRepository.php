<?php

namespace App\ODF\Infrastructure\Repository;

use App\ODF\Domain\Entity\Order;
use App\ODF\Domain\Repository\OrderRepositoryInterface;
use App\ODF\Domain\ValueObject\OrderNumber;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private readonly Connection $wavesoftConnection
    ) {}

    public function findByPcdId(int $pcdid): ?Order
    {
        $sql = "SELECT 
                    p.PCDID,
                    p.PCDNUM,
                    p.PITCODE,
                    p.PICCODE,
                    pp.UNIQUEID,
                    p.MEMOID,
                    pp.STATUS,
                    pp.MESSAGE
                FROM PIECEDIVERS p
                LEFT JOIN PIECEDIVERS_P pp ON pp.PCDID = p.PCDID
                WHERE p.PCDID = :pcdid";

        $result = $this->wavesoftConnection->executeQuery($sql, [
            'pcdid' => $pcdid
        ])->fetchAssociative();

        if (!$result) {
            return null;
        }

        return new Order(
            pcdid: $result['PCDID'],
            pcdnum: $result['PCDNUM'],
            pitcode: $result['PITCODE'],
            piccode: $result['PICCODE'],
            uniqueId: $result['UNIQUEID'],
            memoId: $result['MEMOID'],
            status: $result['STATUS'],
            message: $result['MESSAGE']
        );
    }

    public function updateOrderNumber(int $pcdid, string $orderNumber): void
    {
        $this->wavesoftConnection->executeStatement(
            "UPDATE PIECEDIVERS 
            SET PCDNUMEXT = CONCAT('Order Trimble : ', :orderNumber)
            WHERE PCDID = :pcdid",
            [
                'orderNumber' => $orderNumber,
                'pcdid' => $pcdid
            ]
        );
    }
}

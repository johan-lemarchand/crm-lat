<?php

namespace App\Tests\Unit\ODF\Application\Query\GetOrderStatus;

use App\ODF\Application\Query\GetOrderStatus\GetOrderStatusQuery;
use App\ODF\Application\Query\GetOrderStatus\GetOrderStatusHandler;
use App\ODF\Domain\Repository\PieceDetailsRepository;
use PHPUnit\Framework\TestCase;

class GetOrderStatusHandlerTest extends TestCase
{
    private GetOrderStatusHandler $handler;
    private PieceDetailsRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PieceDetailsRepository::class);
        $this->handler = new GetOrderStatusHandler($this->repository);
    }

    public function testGetExistingOrderStatus(): void
    {
        $expectedStatus = [
            'PCDID' => 123,
            'PCDNUM' => 'ODF123',
            'PCDSTATUS' => 'PENDING',
            'PCDLOCK' => 0
        ];

        $query = new GetOrderStatusQuery('ODF123');

        $this->repository->expects($this->once())
            ->method('findByOrderNumber')
            ->with('ODF123')
            ->willReturn($expectedStatus);

        $result = $this->handler->__invoke($query);

        $this->assertEquals($expectedStatus, $result);
    }

    public function testGetNonExistingOrderStatus(): void
    {
        $query = new GetOrderStatusQuery('ODF999');

        $this->repository->expects($this->once())
            ->method('findByOrderNumber')
            ->with('ODF999')
            ->willReturn(null);

        $result = $this->handler->__invoke($query);

        $this->assertNull($result);
    }

    public function testGetOrderStatusWithEmptyOrderNumber(): void
    {
        $query = new GetOrderStatusQuery('');

        $result = $this->handler->__invoke($query);

        $this->assertNull($result);
    }
}

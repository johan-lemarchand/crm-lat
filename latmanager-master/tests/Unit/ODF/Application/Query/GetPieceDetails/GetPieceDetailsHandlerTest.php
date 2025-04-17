<?php

namespace App\Tests\Unit\ODF\Application\Query\GetPieceDetails;

use App\ODF\Application\Query\GetPieceDetails\GetPieceDetailsQuery;
use App\ODF\Application\Query\GetPieceDetails\GetPieceDetailsHandler;
use App\ODF\Domain\Repository\PieceDetailsRepository;
use PHPUnit\Framework\TestCase;

class GetPieceDetailsHandlerTest extends TestCase
{
    private GetPieceDetailsHandler $handler;
    private PieceDetailsRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PieceDetailsRepository::class);
        $this->handler = new GetPieceDetailsHandler($this->repository);
    }

    public function testGetExistingPieceDetails(): void
    {
        $expectedDetails = [
            [
                'PCDID' => 123,
                'PCDNUM' => 'ODF123',
                'ARTCODE' => 'ART001',
                'PCDQTE' => 5,
                'PCDPRIX' => 10.50
            ]
        ];

        $query = new GetPieceDetailsQuery(123);

        $this->repository->expects($this->once())
            ->method('findByPcdid')
            ->with(123)
            ->willReturn($expectedDetails);

        $result = $this->handler->__invoke($query);

        $this->assertEquals($expectedDetails, $result);
    }

    public function testGetNonExistingPieceDetails(): void
    {
        $query = new GetPieceDetailsQuery(999);

        $this->repository->expects($this->once())
            ->method('findByPcdid')
            ->with(999)
            ->willReturn([]);

        $result = $this->handler->__invoke($query);

        $this->assertEmpty($result);
    }
}

<?php

namespace App\Tests\Unit\ODF\Domain\Service;

use App\ODF\Domain\Repository\PieceDetailsRepository;
use App\ODF\Domain\Service\ManufacturingService;
use App\ODF\Domain\Service\ValidationService;
use PHPUnit\Framework\TestCase;
use Doctrine\DBAL\Connection;

class ManufacturingServiceTest extends TestCase
{
    private ManufacturingService $service;
    private PieceDetailsRepository $pieceDetailsRepository;
    private ValidationService $validationService;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->pieceDetailsRepository = $this->createMock(PieceDetailsRepository::class);
        $this->validationService = $this->createMock(ValidationService::class);
        $this->connection = $this->createMock(Connection::class);

        $this->service = new ManufacturingService(
            $this->pieceDetailsRepository,
            $this->validationService,
            $this->connection
        );
    }

    public function testGetManufacturingDetailsSuccess(): void
    {
        $pcdid = 123;
        $pieceDetails = [
            [
                'PCDNUM' => 'ODF123',
                'PLDTYPE' => 'L',
                'PLDSTOTID' => 1
            ],
            [
                'PLDTYPE' => 'C',
                'PLDPEREID' => 1
            ]
        ];

        $this->pieceDetailsRepository->expects($this->once())
            ->method('findByPcdid')
            ->with($pcdid)
            ->willReturn($pieceDetails);

        $this->pieceDetailsRepository->expects($this->once())
            ->method('findCodeAffaire')
            ->with($pcdid)
            ->willReturn('AFF001');

        $result = $this->service->getManufacturingDetails($pcdid);

        $this->assertIsArray($result);
        $this->assertEquals('ODF123', $result['pcdnum']);
        $this->assertEquals('AFF001', $result['codeAffaire']);
        $this->assertEquals($pieceDetails, $result['pieceDetails']);
    }

    public function testGetManufacturingDetailsNoPieceDetails(): void
    {
        $pcdid = 123;

        $this->pieceDetailsRepository->expects($this->once())
            ->method('findByPcdid')
            ->with($pcdid)
            ->willReturn([]);

        $result = $this->service->getManufacturingDetails($pcdid);

        $this->assertNull($result);
    }

    public function testCreateManufacturingOrderSuccess(): void
    {
        $data = [
            'pcdnum' => 'ODF123',
            'codeAffaire' => 'AFF001',
            'pieceDetails' => [
                [
                    'PCDNUM' => 'ODF123',
                    'PLDTYPE' => 'L',
                    'PLDSTOTID' => 1
                ]
            ],
            'articles' => [
                [
                    'PCDNUM' => 'ODF123',
                    'coupons' => []
                ]
            ]
        ];

        $this->validationService->expects($this->once())
            ->method('validateManufacturingOrder')
            ->with($data)
            ->willReturn(true);

        $this->connection->expects($this->once())
            ->method('beginTransaction');

        $this->connection->expects($this->once())
            ->method('commit');

        $result = $this->service->createManufacturingOrder($data);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testCreateManufacturingOrderValidationFailure(): void
    {
        $data = [
            'pcdnum' => 'ODF123',
            'invalid' => 'data'
        ];

        $this->validationService->expects($this->once())
            ->method('validateManufacturingOrder')
            ->with($data)
            ->willReturn(false);

        $result = $this->service->createManufacturingOrder($data);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('validation', $result['message']);
    }
}

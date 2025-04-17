<?php

namespace App\Tests\Unit\ODF\Domain\Service;

use App\ODF\Domain\Repository\PieceDetailsRepository;
use App\ODF\Domain\Service\CouponService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CouponServiceTest extends TestCase
{
    private CouponService $service;
    private PieceDetailsRepository $pieceDetailsRepository;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->pieceDetailsRepository = $this->createMock(PieceDetailsRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new CouponService(
            $this->pieceDetailsRepository,
            $this->logger
        );
    }

    public function testProcessCouponsSuccess(): void
    {
        $pieceDetails = [
            'pieceDetails' => [
                [
                    'PCDNUM' => 'ODF123',
                    'ARTID' => 'ART001',
                    'PLDTYPE' => 'C'
                ]
            ]
        ];

        $this->pieceDetailsRepository->expects($this->once())
            ->method('getNumberSerieCoupon')
            ->willReturn('SER001');

        $this->pieceDetailsRepository->expects($this->once())
            ->method('getCouponCump')
            ->willReturn(100.0);

        $result = $this->service->processCoupons($pieceDetails);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('articles', $result);
        $this->assertCount(1, $result['articles']);
        $this->assertArrayHasKey('coupons', $result['articles'][0]);
    }

    public function testProcessCouponsWithInvalidPieceDetails(): void
    {
        $pieceDetails = [
            'pieceDetails' => [
                [
                    'PCDNUM' => 'ODF123',
                    'ARTID' => 'ART001',
                    'PLDTYPE' => 'L' // Not a coupon type
                ]
            ]
        ];

        $result = $this->service->processCoupons($pieceDetails);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('articles', $result);
        $this->assertCount(1, $result['articles']);
        $this->assertEmpty($result['articles'][0]['coupons']);
    }

    public function testProcessCouponsWithNoSerialNumber(): void
    {
        $pieceDetails = [
            'pieceDetails' => [
                [
                    'PCDNUM' => 'ODF123',
                    'ARTID' => 'ART001',
                    'PLDTYPE' => 'C'
                ]
            ]
        ];

        $this->pieceDetailsRepository->expects($this->once())
            ->method('getNumberSerieCoupon')
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning');

        $result = $this->service->processCoupons($pieceDetails);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('articles', $result);
        $this->assertCount(1, $result['articles']);
        $this->assertEmpty($result['articles'][0]['coupons']);
    }
}

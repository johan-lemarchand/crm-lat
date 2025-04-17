<?php

namespace App\Tests\Unit\ODF\Application\Command\CreateManufacturingOrder;

use App\ODF\Application\Command\CreateManufacturingOrder\CreateManufacturingOrderCommand;
use App\ODF\Application\Command\CreateManufacturingOrder\CreateManufacturingOrderHandler;
use App\ODF\Domain\Service\ManufacturingService;
use App\ODF\Domain\Service\CouponService;
use App\ODF\Domain\Service\EventService;
use App\ODF\Infrastructure\Service\Timer;
use App\ODF\Infrastructure\Service\MemoAndApiService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateManufacturingOrderHandlerTest extends TestCase
{
    private CreateManufacturingOrderHandler $handler;
    private ManufacturingService $manufacturingService;
    private CouponService $couponService;
    private Timer $timer;
    private EventService $eventService;
    private MemoAndApiService $memoAndApiService;
    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->manufacturingService = $this->createMock(ManufacturingService::class);
        $this->couponService = $this->createMock(CouponService::class);
        $this->timer = $this->createMock(Timer::class);
        $this->eventService = $this->createMock(EventService::class);
        $this->memoAndApiService = $this->createMock(MemoAndApiService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->handler = new CreateManufacturingOrderHandler(
            $this->manufacturingService,
            $this->couponService,
            $this->timer,
            $this->eventService,
            $this->memoAndApiService,
            $this->messageBus
        );
    }

    public function testSuccessfulManufacturingOrderCreation(): void
    {
        $command = new CreateManufacturingOrderCommand(
            123,
            'ODF123',
            ['activations' => []],
            'testUser',
            456
        );

        $manufacturingDetails = [
            'pieceDetails' => [
                ['PCDNUM' => 'ODF123', 'PLDTYPE' => 'L', 'PLDSTOTID' => 1],
                ['PLDTYPE' => 'C', 'PLDPEREID' => 1]
            ],
            'codeAffaire' => 'AFF001',
            'pcdnum' => 'ODF123'
        ];

        $processedItems = [
            'articles' => [
                [
                    'PCDNUM' => 'ODF123',
                    'coupons' => []
                ]
            ]
        ];

        $this->manufacturingService->expects($this->once())
            ->method('getManufacturingDetails')
            ->with(123)
            ->willReturn($manufacturingDetails);

        $this->couponService->expects($this->once())
            ->method('processCoupons')
            ->willReturn($processedItems);

        $this->manufacturingService->expects($this->once())
            ->method('createManufacturingOrder')
            ->willReturn(['status' => 'success']);

        $this->memoAndApiService->expects($this->once())
            ->method('getMemoText')
            ->willReturn('Memo text');

        $result = $this->handler->__invoke($command);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testFailedManufacturingOrderCreation(): void
    {
        $command = new CreateManufacturingOrderCommand(
            123,
            'ODF123',
            ['activations' => []],
            'testUser',
            456
        );

        $this->manufacturingService->expects($this->once())
            ->method('getManufacturingDetails')
            ->willReturn(null);

        $result = $this->handler->__invoke($command);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Impossible de récupérer les détails de fabrication', $result['message']);
    }

    public function testInvalidActivationData(): void
    {
        $command = new CreateManufacturingOrderCommand(
            123,
            'ODF123',
            'invalid json',
            'testUser',
            456
        );

        $result = $this->handler->__invoke($command);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('traitement des données', $result['message']);
    }
}

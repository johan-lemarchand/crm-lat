<?php

namespace App\Tests\Unit\ODF\Infrastructure\Controller;

use App\ODF\Application\Command\CreateOrder\CreateOrderCommand;
use App\ODF\Application\Query\GetOrderDetails\GetOrderDetailsQuery;
use App\ODF\Application\Query\GetOrderStatus\GetOrderStatusQuery;
use App\ODF\Domain\ValueObject\OrderResult;
use App\ODF\Infrastructure\Controller\OrderController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class OrderControllerTest extends TestCase
{
    private OrderController $controller;
    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->controller = new OrderController($this->commandBus, $this->queryBus);
    }

    public function testCreateOrder(): void
    {
        $request = new Request([], [
            'pcdid' => '123',
            'user' => 'testuser',
            'memoId' => 'memo123'
        ]);

        $orderResult = new OrderResult(
            'success',
            'Order created successfully',
            'unique123',
            'memo123',
            ['status' => 'created']
        );

        $envelope = new Envelope(new \stdClass(), [
            new HandledStamp($orderResult, 'handler.name')
        ]);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (CreateOrderCommand $command) {
                return $command->getPcdid() === 123
                    && $command->getUser() === 'testuser'
                    && $command->getMemoId() === 'memo123';
            }))
            ->willReturn($envelope);

        $response = $this->controller->createOrder($request);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('success', $content['status']);
        $this->assertEquals('Order created successfully', $content['message']);
        $this->assertEquals('unique123', $content['uniqueId']);
        $this->assertEquals('memo123', $content['memoId']);
        $this->assertEquals(['status' => 'created'], $content['orderResult']);
    }

    public function testGetOrderStatus(): void
    {
        $orderId = 123;
        $expectedResult = ['status' => 'processing'];

        $envelope = new Envelope(new \stdClass(), [
            new HandledStamp($expectedResult, 'handler.name')
        ]);

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (GetOrderStatusQuery $query) use ($orderId) {
                return $query->getPcdid() === $orderId;
            }))
            ->willReturn($envelope);

        $response = $this->controller->getOrderStatus($orderId);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals($expectedResult, $content);
    }

    public function testGetOrderDetails(): void
    {
        $orderId = 123;
        $expectedResult = [
            'status' => 'success',
            'order' => [
                'pcdid' => 123,
                'articles' => [],
                'totals' => [
                    'totalQuantity' => 0,
                    'totalTypes' => [],
                    'articleCount' => 0
                ]
            ]
        ];

        $envelope = new Envelope(new \stdClass(), [
            new HandledStamp($expectedResult, 'handler.name')
        ]);

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (GetOrderDetailsQuery $query) use ($orderId) {
                return $query->getPcdid() === $orderId;
            }))
            ->willReturn($envelope);

        $response = $this->controller->getOrderDetails($orderId);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals($expectedResult, $content);
    }
}

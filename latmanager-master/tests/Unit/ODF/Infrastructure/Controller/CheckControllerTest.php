<?php

namespace App\Tests\Unit\ODF\Infrastructure\Controller;

use App\ODF\Application\Command\ValidateOrder\ValidateOrderCommand;
use App\ODF\Infrastructure\Controller\CheckController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class CheckControllerTest extends TestCase
{
    private CheckController $controller;
    private MessageBusInterface $commandBus;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->controller = new CheckController($this->commandBus);
    }

    public function testValidateOrder(): void
    {
        $request = new Request([], [
            'pcdid' => '123',
            'user' => 'testuser'
        ]);

        $expectedResult = [
            'status' => 'success',
            'message' => 'Order validation successful',
            'validationResults' => [
                'isValid' => true,
                'errors' => []
            ]
        ];

        $envelope = new Envelope(new \stdClass(), [
            new HandledStamp($expectedResult, 'handler.name')
        ]);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ValidateOrderCommand $command) {
                return $command->getPcdid() === 123
                    && $command->getUser() === 'testuser';
            }))
            ->willReturn($envelope);

        $response = $this->controller->validateOrder($request);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals($expectedResult, $content);
    }

    public function testValidateOrderWithInvalidData(): void
    {
        $request = new Request([], [
            'pcdid' => '',
            'user' => ''
        ]);

        $response = $this->controller->validateOrder($request);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('error', $content['status']);
        $this->assertStringContainsString('Invalid request data', $content['message']);
    }
}

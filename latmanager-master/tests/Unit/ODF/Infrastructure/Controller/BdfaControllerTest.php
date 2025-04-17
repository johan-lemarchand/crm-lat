<?php

namespace App\Tests\Unit\ODF\Infrastructure\Controller;

use App\ODF\Application\Command\ProcessBdfa\ProcessBdfaCommand;
use App\ODF\Infrastructure\Controller\BdfaController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class BdfaControllerTest extends TestCase
{
    private BdfaController $controller;
    private MessageBusInterface $commandBus;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->controller = new BdfaController($this->commandBus);
    }

    public function testProcessBdfa(): void
    {
        $request = new Request([], [
            'pcdid' => '123',
            'user' => 'testuser',
            'bdfaData' => [
                'type' => 'standard',
                'items' => []
            ]
        ]);

        $expectedResult = [
            'status' => 'success',
            'message' => 'BDFA processing completed',
            'processedItems' => []
        ];

        $envelope = new Envelope(new \stdClass(), [
            new HandledStamp($expectedResult, 'handler.name')
        ]);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ProcessBdfaCommand $command) {
                return $command->getPcdid() === 123
                    && $command->getUser() === 'testuser'
                    && is_array($command->getBdfaData());
            }))
            ->willReturn($envelope);

        $response = $this->controller->processBdfa($request);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals($expectedResult, $content);
    }

    public function testProcessBdfaWithInvalidData(): void
    {
        $request = new Request([], [
            'pcdid' => '',
            'user' => '',
            'bdfaData' => null
        ]);

        $response = $this->controller->processBdfa($request);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('error', $content['status']);
        $this->assertStringContainsString('Invalid BDFA data', $content['message']);
    }
}

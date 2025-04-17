<?php

namespace App\Tests\Unit\ODF\Infrastructure\Controller;

use App\ODF\Application\Command\CreateActivation\CreateActivationCommand;
use App\ODF\Infrastructure\Controller\ActivationController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

class ActivationControllerTest extends TestCase
{
    private ActivationController $controller;
    private MessageBusInterface $commandBus;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->controller = new ActivationController($this->commandBus);
    }

    public function testCreateActivation(): void
    {
        $request = new Request([], [
            'pcdid' => '123',
            'user' => 'testuser',
            'passcodes' => ['code1', 'code2']
        ]);

        $expectedResult = [
            'status' => 'success',
            'message' => 'Activation created successfully',
            'activationId' => 'act123'
        ];

        $envelope = new Envelope(new \stdClass(), [
            new HandledStamp($expectedResult, 'handler.name')
        ]);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (CreateActivationCommand $command) {
                return $command->getPcdid() === 123
                    && $command->getUser() === 'testuser'
                    && $command->getPasscodes() === ['code1', 'code2'];
            }))
            ->willReturn($envelope);

        $response = $this->controller->createActivation($request);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals($expectedResult, $content);
    }

    public function testCreateActivationWithInvalidData(): void
    {
        $request = new Request([], [
            'pcdid' => '',
            'user' => '',
            'passcodes' => []
        ]);

        $response = $this->controller->createActivation($request);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('error', $content['status']);
        $this->assertStringContainsString('Invalid request data', $content['message']);
    }

    public function testCreateActivationWithMissingPasscodes(): void
    {
        $request = new Request([], [
            'pcdid' => '123',
            'user' => 'testuser'
            // passcodes manquants
        ]);

        $response = $this->controller->createActivation($request);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('error', $content['status']);
        $this->assertStringContainsString('Missing passcodes', $content['message']);
    }

    public function testCreateActivationWithInvalidPasscodeFormat(): void
    {
        $request = new Request([], [
            'pcdid' => '123',
            'user' => 'testuser',
            'passcodes' => 'invalid-format' // devrait être un tableau
        ]);

        $response = $this->controller->createActivation($request);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('error', $content['status']);
        $this->assertStringContainsString('Invalid passcodes format', $content['message']);
    }

    public function testCreateActivationHandlerError(): void
    {
        $request = new Request([], [
            'pcdid' => '123',
            'user' => 'testuser',
            'passcodes' => ['code1']
        ]);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new HandlerFailedException(
                new Envelope(new \stdClass()),
                [new \Exception('Handler error')]
            ));

        $response = $this->controller->createActivation($request);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('error', $content['status']);
        $this->assertStringContainsString('Handler error', $content['message']);
    }
}

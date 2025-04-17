<?php

namespace App\Tests\Integration\ODF\Infrastructure\Controller;

use App\ODF\Application\Command\ValidateOrder\ValidateOrderCommand;
use App\ODF\Domain\ValueObject\ValidationResult;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class CheckControllerTest extends WebTestCase
{
    private $client;
    private $commandBus;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        self::getContainer()->set('messenger.bus.command', $this->commandBus);
    }

    public function testSuccessfulCheck(): void
    {
        // Prepare test data
        $requestData = [
            'pcdid' => 123,
            'pcdnum' => 'ODF123'
        ];

        // Mock the command bus response
        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function($command) {
                return $command instanceof ValidateOrderCommand
                    && $command->pcdid === 123
                    && $command->pcdnum === 'ODF123';
            }))
            ->willReturn(ValidationResult::success('ODF123'));

        // Make the request
        $this->client->request(
            'POST',
            '/api/odf/check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $content['status']);
    }

    public function testCheckWithMissingData(): void
    {
        // Make request without required data
        $this->client->request(
            'POST',
            '/api/odf/check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('PCDID et PCDNUM sont requis', $content['messages'][0]['message']);
    }

    public function testCheckWithExistingOrder(): void
    {
        $requestData = [
            'pcdid' => 123,
            'pcdnum' => 'ODF123'
        ];

        // Mock existing order response
        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(ValidationResult::existingOrder('ODF123', 'UNIQUE123', 456));

        $this->client->request(
            'POST',
            '/api/odf/check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('existUniqueId', $content['status']);
        $this->assertEquals('UNIQUE123', $content['uniqueId']);
        $this->assertEquals(456, $content['memoId']);
    }

    public function testCheckWithValidationError(): void
    {
        $requestData = [
            'pcdid' => 123,
            'pcdnum' => 'ODF123'
        ];

        // Mock validation error response
        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(ValidationResult::error('ODF123', 'Quantité invalide'));

        $this->client->request(
            'POST',
            '/api/odf/check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Quantité invalide', $content['messages'][0]['message']);
    }
}

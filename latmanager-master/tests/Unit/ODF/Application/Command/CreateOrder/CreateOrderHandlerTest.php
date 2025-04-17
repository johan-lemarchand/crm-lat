<?php

namespace App\Tests\Unit\ODF\Application\Command\CreateOrder;

use App\ODF\Application\Command\CreateOrder\CreateOrderCommand;
use App\ODF\Application\Command\CreateOrder\CreateOrderHandler;
use App\ODF\Domain\Service\UniqueIdService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

class CreateOrderHandlerTest extends TestCase
{
    private CreateOrderHandler $handler;
    private Connection $connection;
    private UniqueIdService $uniqueIdService;
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->uniqueIdService = $this->createMock(UniqueIdService::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);

        $this->handler = new CreateOrderHandler(
            $this->connection,
            $this->uniqueIdService
        );
    }

    public function testSuccessfulOrderCreation(): void
    {
        $orderData = [
            'AFFNUM' => 'AFF001',
            'articles' => [
                [
                    'ARTCODE' => 'ART001',
                    'PCDQTE' => 5,
                    'PCDPRIX' => 10.50
                ]
            ]
        ];

        $command = new CreateOrderCommand($orderData);

        $this->uniqueIdService->expects($this->once())
            ->method('generateUniqueBtrNumber')
            ->willReturn('BTRODF123');

        $this->connection->expects($this->exactly(2))
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        // Mock piece details insertion
        $this->queryBuilder->expects($this->once())
            ->method('insert')
            ->with('PIECES_DETAILS')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('values')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->connection->expects($this->once())
            ->method('beginTransaction');

        $this->connection->expects($this->once())
            ->method('commit');

        $result = $this->handler->__invoke($command);

        $this->assertTrue($result['success']);
        $this->assertEquals('BTRODF123', $result['orderNumber']);
    }

    public function testFailedOrderCreation(): void
    {
        $orderData = [
            'AFFNUM' => 'AFF001',
            'articles' => [
                [
                    'ARTCODE' => 'ART001',
                    'PCDQTE' => 5,
                    'PCDPRIX' => 10.50
                ]
            ]
        ];

        $command = new CreateOrderCommand($orderData);

        $this->uniqueIdService->expects($this->once())
            ->method('generateUniqueBtrNumber')
            ->willReturn('BTRODF123');

        $this->connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willThrowException(new \Exception('Database error'));

        $this->connection->expects($this->once())
            ->method('beginTransaction');

        $this->connection->expects($this->once())
            ->method('rollBack');

        $result = $this->handler->__invoke($command);

        $this->assertFalse($result['success']);
        $this->assertEquals('Database error', $result['error']);
    }

    public function testOrderCreationWithEmptyArticles(): void
    {
        $orderData = [
            'AFFNUM' => 'AFF001',
            'articles' => []
        ];

        $command = new CreateOrderCommand($orderData);

        $result = $this->handler->__invoke($command);

        $this->assertFalse($result['success']);
        $this->assertEquals('No articles provided', $result['error']);
    }
}

<?php

namespace App\Tests\Unit\ODF\Application\Command\LockOrder;

use App\ODF\Application\Command\LockOrder\LockOrderCommand;
use App\ODF\Application\Command\LockOrder\LockOrderHandler;
use App\ODF\Infrastructure\Service\Timer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

class LockOrderHandlerTest extends TestCase
{
    private LockOrderHandler $handler;
    private Connection $connection;
    private Timer $timer;
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->timer = $this->createMock(Timer::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);

        $this->handler = new LockOrderHandler(
            $this->connection,
            $this->timer
        );
    }

    public function testSuccessfulLock(): void
    {
        $command = new LockOrderCommand(123, 'ODF123');

        // Mock the query builder chain
        $this->connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('update')
            ->with('PIECES_DETAILS')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('set')
            ->with('PCDLOCK', ':lock')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('PCDID = :pcdid')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->connection->expects($this->once())
            ->method('beginTransaction');

        $this->connection->expects($this->once())
            ->method('commit');

        $result = $this->handler->__invoke($command);

        $this->assertTrue($result);
    }

    public function testFailedLock(): void
    {
        $command = new LockOrderCommand(123, 'ODF123');

        $this->connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('update')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('executeStatement')
            ->willReturn(0);

        $this->connection->expects($this->once())
            ->method('beginTransaction');

        $this->connection->expects($this->once())
            ->method('rollBack');

        $result = $this->handler->__invoke($command);

        $this->assertFalse($result);
    }

    public function testLockWithException(): void
    {
        $command = new LockOrderCommand(123, 'ODF123');

        $this->connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willThrowException(new \Exception('Database error'));

        $this->connection->expects($this->once())
            ->method('beginTransaction');

        $this->connection->expects($this->once())
            ->method('rollBack');

        $result = $this->handler->__invoke($command);

        $this->assertFalse($result);
    }
}

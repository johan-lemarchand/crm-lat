<?php

namespace App\Tests\Unit\ODF\Infrastructure\Repository;

use App\ODF\Infrastructure\Repository\DbalPieceDetailsRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

class DbalPieceDetailsRepositoryTest extends TestCase
{
    private DbalPieceDetailsRepository $repository;
    private Connection $connection;
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->repository = new DbalPieceDetailsRepository($this->connection);
    }

    public function testFindByPcdid(): void
    {
        $expectedResult = [
            [
                'PCDID' => 123,
                'PCDNUM' => 'ODF123',
                'ARTCODE' => 'ART001'
            ]
        ];

        $this->connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('select')
            ->with('p.*')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('from')
            ->with('PIECES_DETAILS', 'p')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('p.PCDID = :pcdid')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('pcdid', 123)
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('executeQuery')
            ->willReturn(new class($expectedResult) {
                private $result;
                public function __construct($result) {
                    $this->result = $result;
                }
                public function fetchAllAssociative() {
                    return $this->result;
                }
            });

        $result = $this->repository->findByPcdid(123);

        $this->assertEquals($expectedResult, $result);
    }

    public function testExistsBtr(): void
    {
        $this->connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('select')
            ->with('COUNT(p.PCDID)')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('from')
            ->with('PIECES_DETAILS', 'p')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('p.PCDNUM = :btrNumber')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('btrNumber', 'BTR123')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('executeQuery')
            ->willReturn(new class {
                public function fetchOne() {
                    return 1;
                }
            });

        $result = $this->repository->existsBtr('BTR123');

        $this->assertTrue($result);
    }
}

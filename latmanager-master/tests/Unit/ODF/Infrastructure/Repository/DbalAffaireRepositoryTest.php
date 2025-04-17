<?php

namespace App\Tests\Unit\ODF\Infrastructure\Repository;

use App\ODF\Infrastructure\Repository\DbalAffaireRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

class DbalAffaireRepositoryTest extends TestCase
{
    private DbalAffaireRepository $repository;
    private Connection $connection;
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->repository = new DbalAffaireRepository($this->connection);
    }

    public function testFindByPcdid(): void
    {
        $expectedResult = [
            'AFFNUM' => 'AFF001',
            'AFFLIB' => 'Test Affaire',
            'AFFSTATUS' => 'OPEN'
        ];

        $this->connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('select')
            ->with('a.*')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('from')
            ->with('AFFAIRES', 'a')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->once())
            ->method('join')
            ->with('a', 'PIECES_DETAILS', 'p', 'p.AFFNUM = a.AFFNUM')
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
                public function fetchAssociative() {
                    return $this->result;
                }
            });

        $result = $this->repository->findByPcdid(123);

        $this->assertEquals($expectedResult, $result);
    }

    public function testFindByPcdidNotFound(): void
    {
        $this->connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('executeQuery')
            ->willReturn(new class {
                public function fetchAssociative() {
                    return false;
                }
            });

        $result = $this->repository->findByPcdid(999);

        $this->assertEmpty($result);
    }
}

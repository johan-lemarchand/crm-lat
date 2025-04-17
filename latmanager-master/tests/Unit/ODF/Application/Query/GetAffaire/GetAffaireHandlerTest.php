<?php

namespace App\Tests\Unit\ODF\Application\Query\GetAffaire;

use App\ODF\Application\Query\GetAffaire\GetAffaireQuery;
use App\ODF\Application\Query\GetAffaire\GetAffaireHandler;
use App\ODF\Domain\Repository\AffaireRepository;
use PHPUnit\Framework\TestCase;

class GetAffaireHandlerTest extends TestCase
{
    private GetAffaireHandler $handler;
    private AffaireRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AffaireRepository::class);
        $this->handler = new GetAffaireHandler($this->repository);
    }

    public function testGetExistingAffaire(): void
    {
        $expectedAffaire = [
            'AFFNUM' => 'AFF001',
            'AFFLIB' => 'Test Affaire',
            'AFFSTATUS' => 'OPEN'
        ];

        $query = new GetAffaireQuery(123);

        $this->repository->expects($this->once())
            ->method('findByPcdid')
            ->with(123)
            ->willReturn($expectedAffaire);

        $result = $this->handler->__invoke($query);

        $this->assertEquals($expectedAffaire, $result);
    }

    public function testGetNonExistingAffaire(): void
    {
        $query = new GetAffaireQuery(999);

        $this->repository->expects($this->once())
            ->method('findByPcdid')
            ->with(999)
            ->willReturn([]);

        $result = $this->handler->__invoke($query);

        $this->assertEmpty($result);
    }
}

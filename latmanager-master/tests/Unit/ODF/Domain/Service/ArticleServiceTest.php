<?php

namespace App\Tests\Unit\ODF\Domain\Service;

use App\ODF\Domain\Repository\PieceDetailsRepository;
use App\ODF\Domain\Service\ArticleService;
use App\ODF\Domain\Service\ValidationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ArticleServiceTest extends TestCase
{
    private ArticleService $service;
    private PieceDetailsRepository $pieceDetailsRepository;
    private ValidationService $validationService;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->pieceDetailsRepository = $this->createMock(PieceDetailsRepository::class);
        $this->validationService = $this->createMock(ValidationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ArticleService(
            $this->pieceDetailsRepository,
            $this->validationService,
            $this->logger
        );
    }

    public function testProcessArticlesSuccess(): void
    {
        $pieceDetails = [
            'pieceDetails' => [
                [
                    'ARTID' => 'ART001',
                    'PCDNUM' => 'ODF123',
                    'PLDQTE' => 2,
                    'PLDTYPE' => 'S'
                ]
            ]
        ];

        $this->validationService->expects($this->once())
            ->method('validateArticle')
            ->willReturn(true);

        $result = $this->service->processArticles($pieceDetails);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['articles']);
        $this->assertEquals('ART001', $result['articles'][0]['ARTID']);
        $this->assertEquals(2, $result['articles'][0]['quantity']);
    }

    public function testProcessArticlesWithInvalidArticle(): void
    {
        $pieceDetails = [
            'pieceDetails' => [
                [
                    'PCDNUM' => 'ODF123',
                    // Missing ARTID
                ]
            ]
        ];

        $this->logger->expects($this->once())
            ->method('warning');

        $result = $this->service->processArticles($pieceDetails);

        $this->assertEquals('success', $result['status']);
        $this->assertEmpty($result['articles']);
    }

    public function testProcessArticlesWithValidationFailure(): void
    {
        $pieceDetails = [
            'pieceDetails' => [
                [
                    'ARTID' => 'ART001',
                    'PCDNUM' => 'ODF123'
                ]
            ]
        ];

        $this->validationService->expects($this->once())
            ->method('validateArticle')
            ->willReturn(false);

        $this->logger->expects($this->once())
            ->method('warning');

        $result = $this->service->processArticles($pieceDetails);

        $this->assertEquals('success', $result['status']);
        $this->assertEmpty($result['articles']);
    }

    public function testCalculateArticleTotalsSuccess(): void
    {
        $articles = [
            [
                'ARTID' => 'ART001',
                'quantity' => 2,
                'type' => 'S'
            ],
            [
                'ARTID' => 'ART002',
                'quantity' => 3,
                'type' => 'L'
            ]
        ];

        $result = $this->service->calculateArticleTotals($articles);

        $this->assertEquals(5, $result['totalQuantity']);
        $this->assertEquals(2, $result['articleCount']);
        $this->assertEquals(1, $result['totalTypes']['S']);
        $this->assertEquals(1, $result['totalTypes']['L']);
    }

    public function testCalculateArticleTotalsWithDefaultValues(): void
    {
        $articles = [
            [
                'ARTID' => 'ART001'
            ],
            [
                'ARTID' => 'ART002'
            ]
        ];

        $result = $this->service->calculateArticleTotals($articles);

        $this->assertEquals(2, $result['totalQuantity']);
        $this->assertEquals(2, $result['articleCount']);
        $this->assertEquals(2, $result['totalTypes']['S']);
    }
}

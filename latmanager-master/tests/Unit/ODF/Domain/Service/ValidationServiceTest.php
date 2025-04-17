<?php

namespace App\Tests\Unit\ODF\Domain\Service;

use App\ODF\Domain\Service\ValidationService;
use PHPUnit\Framework\TestCase;

class ValidationServiceTest extends TestCase
{
    private ValidationService $validationService;

    protected function setUp(): void
    {
        $this->validationService = new ValidationService();
    }

    public function testCheckArticleDetailsWithValidData(): void
    {
        $pieceDetails = [
            [
                'ARTCODE' => 'ART001',
                'PCDQTE' => 5,
                'PCDPRIX' => 10.50
            ]
        ];

        $result = $this->validationService->checkArticleDetails($pieceDetails);

        $this->assertEquals('success', $result['status']);
        $this->assertEmpty($result['messages']);
        $this->assertCount(1, $result['details']);
        $this->assertEquals('ART001', $result['details'][0]['artcode']);
    }

    public function testCheckArticleDetailsWithMissingArticleCode(): void
    {
        $pieceDetails = [
            [
                'ARTCODE' => '',
                'PCDQTE' => 5,
                'PCDPRIX' => 10.50
            ]
        ];

        $result = $this->validationService->checkArticleDetails($pieceDetails);

        $this->assertEquals('error', $result['status']);
        $this->assertCount(1, $result['messages']);
        $this->assertEquals('Le code article est obligatoire', $result['messages'][0]['message']);
    }

    public function testCheckArticleDetailsWithInvalidQuantity(): void
    {
        $pieceDetails = [
            [
                'ARTCODE' => 'ART001',
                'PCDQTE' => 0,
                'PCDPRIX' => 10.50
            ]
        ];

        $result = $this->validationService->checkArticleDetails($pieceDetails);

        $this->assertEquals('error', $result['status']);
        $this->assertCount(1, $result['messages']);
        $this->assertEquals("La quantité doit être supérieure à 0 pour l'article ART001", $result['messages'][0]['message']);
    }

    public function testCheckArticleDetailsWithInvalidPrice(): void
    {
        $pieceDetails = [
            [
                'ARTCODE' => 'ART001',
                'PCDQTE' => 5,
                'PCDPRIX' => -1
            ]
        ];

        $result = $this->validationService->checkArticleDetails($pieceDetails);

        $this->assertEquals('error', $result['status']);
        $this->assertCount(1, $result['messages']);
        $this->assertEquals("Le prix doit être valide pour l'article ART001", $result['messages'][0]['message']);
    }

    public function testValidateManufacturingOrderSuccess(): void
    {
        $data = [
            'pcdnum' => 'ODF123',
            'codeAffaire' => 'AFF001',
            'pieceDetails' => [
                [
                    'PCDNUM' => 'ODF123',
                    'PLDTYPE' => 'L'
                ]
            ],
            'articles' => [
                [
                    'PCDNUM' => 'ODF123',
                    'coupons' => []
                ]
            ]
        ];

        $result = $this->validationService->validateManufacturingOrder($data);

        $this->assertTrue($result);
    }

    public function testValidateManufacturingOrderMissingRequiredFields(): void
    {
        $data = [
            'pcdnum' => 'ODF123',
            'pieceDetails' => []
        ];

        $result = $this->validationService->validateManufacturingOrder($data);

        $this->assertFalse($result);
    }

    public function testValidateManufacturingOrderEmptyPieceDetails(): void
    {
        $data = [
            'pcdnum' => 'ODF123',
            'codeAffaire' => 'AFF001',
            'pieceDetails' => []
        ];

        $result = $this->validationService->validateManufacturingOrder($data);

        $this->assertFalse($result);
    }

    public function testValidateManufacturingOrderInvalidPieceDetails(): void
    {
        $data = [
            'pcdnum' => 'ODF123',
            'codeAffaire' => 'AFF001',
            'pieceDetails' => [
                [
                    'PCDNUM' => 'ODF123'
                    // Missing PLDTYPE
                ]
            ]
        ];

        $result = $this->validationService->validateManufacturingOrder($data);

        $this->assertFalse($result);
    }

    public function testValidateCouponDataSuccess(): void
    {
        $data = [
            'ARTID' => 'ART001',
            'numeroSerie' => 'SER001'
        ];

        $result = $this->validationService->validateCouponData($data);

        $this->assertTrue($result);
    }

    public function testValidateCouponDataMissingFields(): void
    {
        $data = [
            'ARTID' => 'ART001'
        ];

        $result = $this->validationService->validateCouponData($data);

        $this->assertFalse($result);
    }

    public function testValidateCouponDataEmptySerialNumber(): void
    {
        $data = [
            'ARTID' => 'ART001',
            'numeroSerie' => ''
        ];

        $result = $this->validationService->validateCouponData($data);

        $this->assertFalse($result);
    }
}

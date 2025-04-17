<?php

namespace App\ODF\Domain\Service;

interface ArticleProcessServiceInterface
{
    /**
     * Traite les articles et leurs coupons associés
     *
     * @param array $pieceDetails
     * @return array{
     *     status: string,
     *     messages: array<array{message: string, status: string}>,
     *     details?: array<array{
     *         ligne: string,
     *         article: array{
     *             code: string,
     *             designation: string,
     *             coupon: string|null,
     *             eligible: bool
     *         },
     *         quantite: int,
     *         serie: array{
     *             numero: string,
     *             status: string,
     *             message: string,
     *             message_api: string|null,
     *             manufacturerModel: string|null
     *         }
     *     }>
     * }
     */
    public function processArticlesAndCoupons(array $pieceDetails): array;
}

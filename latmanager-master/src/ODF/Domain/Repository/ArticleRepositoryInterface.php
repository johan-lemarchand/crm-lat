<?php

namespace App\ODF\Domain\Repository;

interface ArticleRepositoryInterface
{
    /**
     * @return array<array{
     *     PLDID: string,
     *     ARTID: int,
     *     ARTCODE: string,
     *     ARTDESIGNATION: string,
     *     PLDQTE: float,
     *     COUPON: string|null,
     *     PLDTYPE: string,
     *     PCDNUM: string
     * }>
     */
    public function findArticlesByPcdid(int $pcdid): array;

    /**
     * @return array<array{
     *     PLDTYPE: string,
     *     ARTCODE: string,
     *     PLDQTE: int
     * }>
     */
    public function findArticlesWithTypes(int $pcdid): array;
} 
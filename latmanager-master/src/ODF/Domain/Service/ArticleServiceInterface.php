<?php

namespace App\ODF\Domain\Service;

interface ArticleServiceInterface
{
    /**
     * Traite les articles d'une pièce
     *
     * @param array $data Les données contenant les détails de la pièce
     * @return array{
     *     status: string,
     *     message?: string,
     *     articles?: array<array{
     *         ARTID: int,
     *         quantity?: int,
     *         ARTREF?: string,
     *         ARTLIB?: string
     *     }>
     * }
     */
    public function processArticles(array $data): array;
}

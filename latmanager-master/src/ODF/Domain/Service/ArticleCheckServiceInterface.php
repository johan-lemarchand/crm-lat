<?php

namespace App\ODF\Domain\Service;

interface ArticleCheckServiceInterface
{
    /**
     * Vérifie les articles et retourne le résultat avec les détails
     */
    public function checkArticles(array $pieceDetails): array;
} 
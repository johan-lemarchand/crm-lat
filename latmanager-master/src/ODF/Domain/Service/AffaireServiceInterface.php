<?php

namespace App\ODF\Domain\Service;

interface AffaireServiceInterface
{
    /**
     * @return array{
     *     status: string,
     *     messages: array<array{message: string, status: string}>,
     *     affaire?: array{code: string, intitule: string}|null
     * }
     */
    public function checkAffaire(int $pcdid): array;

    public function createAffaire(string $affcode, string $affIntitule): int;
    
    public function updateAffaireLinks(int $pcdid, int $affid): void;

    /**
     * @return array{
     *     status: string,
     *     messages: array<array{message: string, status: string}>,
     *     affaire?: array{code: string, intitule: string}|null
     * }
     */
    public function checkAndManageAffaire(int $pcdid): array;
}

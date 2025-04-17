<?php

namespace App\ODF\Domain\Repository;

interface AffaireRepositoryInterface
{
    /**
     * @return array{
     *     affaire: array{
     *         code: string,
     *         intitule: string
     *     }|null,
     *     status: string,
     *     messages: array<array{message: string, status: string}>
     * }
     */
    public function findByAffaireId(int $pcdid): array;

    /**
     * @param int $affaireId
     * @return array<string, mixed>|false
     */
    public function findByAffaireIdWithDetails(int $affaireId): array|false;

    public function findAffaireByPcdid(int $pcdid): ?array;
    public function findAffaireByCode(string $affcode): ?array;
    public function createAffaire(string $affcode, string $affIntitule): int;
    public function updateAffaireLinks(int $pcdid, int $affid): void;
}

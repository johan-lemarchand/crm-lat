<?php

namespace App\ODF\Infrastructure\Repository;

use App\ODF\Domain\Repository\MemoRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class MemoRepository implements MemoRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    /**
     * @throws Exception
     */
    public function findMemoById(int $memoId): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT MEMO FROM MEMOS WHERE MEMOID = ?',
            [$memoId]
        );

        return $result ?: null;
    }

    /**
     * @throws Exception
     */
    public function updateMemo(int $memoId, string $memo): void
    {
        $this->connection->executeStatement(
            'UPDATE MEMOS SET MEMO = ? WHERE MEMOID = ?',
            [$memo, $memoId]
        );
    }

    /**
     * Récupère le texte d'un mémo par son ID
     * 
     * @param int $memoId ID du mémo
     * @return string|null Le texte du mémo ou null si non trouvé
     */
    public function getMemoText(int $memoId): ?string
    {
        try {
            $result = $this->connection->fetchOne(
                'SELECT MEMO FROM MEMOS WHERE MEMOID = ?',
                [$memoId]
            );
            
            return $result ?: null;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération du texte du mémo', [
                'error' => $e->getMessage(),
                'memoId' => $memoId
            ]);
            
            return null;
        }
    }
}
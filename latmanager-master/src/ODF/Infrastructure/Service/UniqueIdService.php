<?php

namespace App\ODF\Infrastructure\Service;

use App\ODF\Domain\Repository\PieceDetailsRepositoryInterface;
use App\ODF\Domain\Service\UniqueIdServiceInterface;
use Exception;

readonly class UniqueIdService implements UniqueIdServiceInterface
{
    public function __construct(
        private PieceDetailsRepositoryInterface $repository
    ) {}

    /**
     * @throws Exception
     */
    public function checkCloseOdfAndUniqueId(int $pcdid): ?array
    {
        $result = $this->repository->findUniqueIdByPcdid($pcdid);

        if (!$result) {
            return [
                'status' => 'error',
                'messages' => [[
                    'message' => 'ODF non trouvé',
                    'status' => 'error'
                ]]
            ];
        }

        if ($result['PCDISCLOS'] === 'O') {
            return [
                'status' => 'error',
                'isClosed' => true,
                'messages' => [[
                    'message' => 'Cet ODF est déjà clôturé',
                    'status' => 'error'
                ]]
            ];
        }

        if ($result['UNIQUEID']) {
            return [
                'status' => 'info',
                'uniqueId' => $result['UNIQUEID'],
                'memoId' => $result['MEMOID'],
                'exists' => true,
                'pcdnum' => $result['PCDNUM'] ?? null
            ];
        }

        return [
            'status' => 'success',
            'exists' => false,
            'pcdnum' => $result['PCDNUM'] ?? null
        ];
    }

    public function updateUniqueId(int $pcdid, string $uniqueId): void
    {
        $this->repository->updateUniqueId($pcdid, $uniqueId);
    }
}

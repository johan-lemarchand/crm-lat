<?php

namespace App\ODF\Infrastructure\Service;

use App\ODF\Domain\Repository\MemoRepositoryInterface;
use App\ODF\Domain\Repository\PieceDiversRepositoryInterface;
use App\ODF\Domain\Service\MemoAndApiServiceInterface;
use Doctrine\DBAL\Connection;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MemoAndApiService implements MemoAndApiServiceInterface
{
    private const MEMO_MAX_LENGTH = 5000;
    private const START_TAG = "!! Début";
    private const END_TAG = "!! Fin";
    private const SAUTDELIGNE = "\r\n";

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly MemoRepositoryInterface $memoRepository,
        private readonly PieceDiversRepositoryInterface $pieceDiversRepository
    ) {}

    /**
     * @throws Exception
     */
    public function updateMemo(int $memoId, array $messages, string $user): void
    {
        try {
            $dateTime = date('d/m/Y H:i:s');
            $formattedMessages = [];
            
            foreach ($messages as $message) {
                $title = $message['title'];
                $content = $message['content'];
                $status = $message['status'];
                
                $statusText = $status === 'success' ? '[SUCCESS]' : '[ERROR]';
                $formattedMessages[] = "[$dateTime - $user - $title] $statusText $content";
            }
            
            $existingMemo = $this->memoRepository->findMemoById($memoId) ?? '';
            $newMessages = implode(self::SAUTDELIGNE, array_reverse($formattedMessages));

            preg_match('/^(.*?)' . preg_quote(self::START_TAG) . '(.*?)' . preg_quote(self::END_TAG) . '(.*)$/s', $existingMemo, $matches);

            $beforeTags = $matches[1] ?? '';
            $betweenTags = $matches[2] ?? '';
            $afterTags = $matches[3] ?? '';

            if (empty($betweenTags)) {
                $newMemo = $existingMemo . self::SAUTDELIGNE . self::START_TAG . self::SAUTDELIGNE . 
                          $newMessages . self::SAUTDELIGNE . self::END_TAG;
            } else {
                $newMemo = $beforeTags . self::START_TAG . self::SAUTDELIGNE . 
                          $newMessages . self::SAUTDELIGNE . $betweenTags . self::END_TAG . $afterTags;
            }

            if (strlen($newMemo) > self::MEMO_MAX_LENGTH) {
                $this->logger->warning('Memo trop long, troncature nécessaire', [
                    'memoId' => $memoId,
                    'length' => strlen($newMemo)
                ]);
                $newMemo = substr($newMemo, 0, self::MEMO_MAX_LENGTH);
            }

            $this->memoRepository->updateMemo($memoId, $newMemo);

            $this->logger->info('Memo mis à jour avec succès', [
                'memoId' => $memoId,
                'user' => $user
            ]);

        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour du memo', [
                'memoId' => $memoId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function updatePieceDiversApi(int $pcdid, string $user, array $data): void
    {
        try {
            $this->pieceDiversRepository->updateStatus($pcdid,$user,$data);

            $this->logger->info('Pièce diverse mise à jour avec succès', [
                'pcdid' => $pcdid,
                'data' => $data
            ]);

        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour de la pièce diverse', [
                'pcdid' => $pcdid,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}

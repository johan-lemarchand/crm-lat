<?php

namespace App\ODF\Application\Command\LockOrder;

use Doctrine\DBAL\Connection;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class LockOrderHandler
{
    public function __construct(
        private Connection         $connection,
        private OdfExecutionLogger $executionLogger
    ) {}

    public function __invoke(LockOrderCommand $command): bool
    {
        $handlerStartTime = $this->executionLogger->startHandler(
            'LockOrderHandler',
            [
                'pcdid' => $command->pcdid,
                'pcdnum' => $command->pcdnum
            ]
        );

        try {
            $this->executionLogger->addStep(
                'Début verrouillage',
                'info',
                sprintf('Tentative de verrouillage de la pièce %s', $command->pcdnum)
            );

            $this->connection->beginTransaction();

            $qb = $this->connection->createQueryBuilder();
            $locked = $qb
                ->update('PIECES_DETAILS')
                ->set('PCDLOCK', ':lock')
                ->where('PCDID = :pcdid')
                ->setParameter('lock', 1)
                ->setParameter('pcdid', $command->pcdid)
                ->executeStatement();

            if ($locked) {
                $this->connection->commit();
                $this->executionLogger->addStep(
                    'Verrouillage réussi',
                    'success',
                    sprintf('Pièce %s verrouillée avec succès', $command->pcdnum)
                );

                $this->executionLogger->finishHandler('LockOrderHandler', $handlerStartTime, [
                    'status' => 'success',
                    'pcdnum' => $command->pcdnum
                ]);

                return true;
            }

            $this->connection->rollBack();
            $this->executionLogger->addStep(
                'Échec verrouillage',
                'error',
                sprintf('Impossible de verrouiller la pièce %s', $command->pcdnum)
            );

            $this->executionLogger->finishHandler('LockOrderHandler', $handlerStartTime, [
                'status' => 'error',
                'reason' => 'lock_failed'
            ]);

            return false;
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $this->executionLogger->logError(
                'Erreur verrouillage',
                sprintf('Erreur lors du verrouillage de la pièce %s', $command->pcdnum),
                $e
            );

            return false;
        }
    }
}

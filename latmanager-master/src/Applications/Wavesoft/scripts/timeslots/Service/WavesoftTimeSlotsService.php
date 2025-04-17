<?php

namespace App\Applications\Wavesoft\scripts\timeslots\Service;

use App\Applications\Wavesoft\WavesoftClient;
use Doctrine\DBAL\Connection;
use Exception;
use Psr\Log\LoggerInterface;

readonly class WavesoftTimeSlotsService
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private WavesoftClient $wavesoft,
    ) {
    }

    /**
     * Met à jour ou crée un créneau dans Wavesoft.
     *
     * @throws Exception
     */
    public function upsertTimeSlot(array $timeSlot): bool
    {
        try {
            $sql = <<<SQL
                MERGE EXT_CRENEAU_PRAXEDO AS ECP
                USING (
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ) AS new (TYPE_CRENEAU, ID_AGENT, DATE_DEBUT, DATE_FIN, DATE_UPDATE, ID_CRENEAU_PRAXEDO, COMPLETE_DAY, ISDELETED)
                ON (ECP.ID_CRENEAU_PRAXEDO = new.ID_CRENEAU_PRAXEDO)
                WHEN MATCHED THEN
                    UPDATE SET 
                        TYPE_CRENEAU = new.TYPE_CRENEAU,
                        DATE_UPDATE = new.DATE_UPDATE,
                        DATE_DEBUT = new.DATE_DEBUT,
                        DATE_FIN = new.DATE_FIN,
                        COMPLETE_DAY = new.COMPLETE_DAY,
                        ISDELETED = 'N'
                WHEN NOT MATCHED THEN
                    INSERT (TYPE_CRENEAU, ID_AGENT, DATE_DEBUT, DATE_FIN, DATE_UPDATE, ID_CRENEAU_PRAXEDO, COMPLETE_DAY, ISDELETED)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?);
SQL;

            $params = [
                $timeSlot['TYPE_CRENEAU'],
                $timeSlot['ID_AGENT'],
                $timeSlot['DATE_DEBUT'],
                $timeSlot['DATE_FIN'],
                $timeSlot['DATE_UPDATE'],
                $timeSlot['ID_CRENEAU_PRAXEDO'],
                $timeSlot['COMPLETE_DAY'],
                $timeSlot['ISDELETED'],
            ];

            $stmt = $this->wavesoft->query($sql);
            $result = $stmt->execute(array_merge($params, $params));

            $this->logger->info('Créneau mis à jour dans Wavesoft', [
                'id_creneau' => $timeSlot['ID_CRENEAU_PRAXEDO'],
                'agent' => $timeSlot['ID_AGENT'],
            ]);

            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour du créneau dans Wavesoft', [
                'error' => $e->getMessage(),
                'timeSlot' => $timeSlot,
            ]);
            throw $e;
        }
    }

    /**
     * Récupère les créneaux depuis Wavesoft.
     *
     * @throws Exception
     */
    public function getTimeSlots(\DateTime $startDate): array
    {
        try {
            $sql = 'SELECT ID_CRENEAU_PRAXEDO FROM EXT_CRENEAU_PRAXEDO WHERE DATE_DEBUT >= ? AND ISDELETED = ?';
            $stmt = $this->wavesoft->query($sql);
            $stmt->execute([$startDate->format('d/m/Y H:i:s.v'), 'N']);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la récupération des créneaux Wavesoft', [
                'error' => $e->getMessage(),
                'start_date' => $startDate->format('Y-m-d H:i:s'),
            ]);
            throw $e;
        }
    }

    /**
     * Marque les créneaux comme supprimés dans Wavesoft.
     * @throws \DateMalformedStringException
     */
    public function markTimeSlotsAsDeleted(array $timeSlotIds): bool
    {
        try {
            if (empty($timeSlotIds)) {
                return false;
            }

            $placeholders = str_repeat('?,', count($timeSlotIds) - 1).'?';
            $sql = <<<SQL
                UPDATE EXT_CRENEAU_PRAXEDO
                SET ISDELETED = 'O',
                    DATE_UPDATE = ?
                WHERE ID_CRENEAU_PRAXEDO IN ($placeholders)
SQL;

            $params = array_merge(
                [(new \DateTime())->format('d/m/Y H:i:s.v')],
                $timeSlotIds
            );

            $stmt = $this->wavesoft->query($sql);
            $result = $stmt->execute($params);

            $this->logger->info('Créneaux marqués comme supprimés dans Wavesoft', [
                'count' => count($timeSlotIds),
            ]);

            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('Erreur lors du marquage des créneaux comme supprimés', [
                'error' => $e->getMessage(),
                'timeSlotIds' => $timeSlotIds,
            ]);
            throw $e;
        }
    }
}

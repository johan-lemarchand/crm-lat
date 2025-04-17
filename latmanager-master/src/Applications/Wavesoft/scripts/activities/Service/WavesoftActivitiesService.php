<?php

namespace App\Applications\Wavesoft\scripts\activities\Service;

use App\Applications\Wavesoft\WavesoftClient;
use Doctrine\DBAL\Connection;
use Exception;
use Psr\Log\LoggerInterface;

readonly class WavesoftActivitiesService
{
    private const ACTIVITY_TYPES = [
        'MO-FORM' => 'Formation',
        'Int' => 'Montage',
        'NET_CAM' => 'Nettoyage / Rangement Camion',
        'N2F' => 'Notes de Frais',
        'PLANNING' => 'Organisation Planning',
        'REP' => 'Repas',
        'REUNION_TECH' => 'Réunion Technique',
        'SALON' => 'Salon',
        'SAV_SUPPORT' => 'SAV (Support)',
        'SAV_TERRAIN' => 'SAV (Terrain)',
        'TRAJET' => 'Trajet',
        'REDAC_TECH' => 'Rédaction Technique',
        'COMMERCE' => 'Commerce',
        'SERVICE_REPAR' => 'Service Réparation (Venon)',
        'AIDE_COMMERCE' => 'Commerce',
        'BUREAU' => 'Autres',
    ];

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private WavesoftClient $wavesoft,
    ) {
    }

    /**
     * Met à jour ou crée une activité dans Wavesoft.
     *
     * @throws Exception
     */
    public function upsertActivity(array $activity): bool
    {
        try {
            $dateDebut = \DateTime::createFromFormat('d/m/Y H:i:s.v', $activity['DATE_DEBUT']);
            $dateFin = \DateTime::createFromFormat('d/m/Y H:i:s.v', $activity['DATE_FIN']);
            $dateUpdate = \DateTime::createFromFormat('d/m/Y H:i:s.v', $activity['DATE_UPDATE']);
            $dateUpdatePraxedo = \DateTime::createFromFormat('d/m/Y H:i:s.v', $activity['DATE_UPDATE_PRAXEDO']);

            if (!$dateDebut || !$dateFin || !$dateUpdate) {
                throw new Exception('Format de date invalide: DATE_DEBUT='.$activity['DATE_DEBUT'].', DATE_FIN='.$activity['DATE_FIN'].', DATE_UPDATE='.$activity['DATE_UPDATE']);
            }

            $sql = 'MERGE EXT_ACTIVITE_PRAXEDO AS EAP
                USING (
                    SELECT 
                        ? as TYPE_ACTIVITE,
                        ? as ID_AGENT,
                        CONVERT(DATETIME, ?, 121) as DATE_DEBUT,
                        CONVERT(DATETIME, ?, 121) as DATE_FIN,
                        CONVERT(DATETIME, ?, 121) as DATE_UPDATE,
                        CONVERT(DATETIME, ?, 121) as DATE_UPDATE_PRAXEDO,
                        ? as INTER_PRAXEDO,
                        ? as ID_ACTIVITE_PRAXEDO,
                        ? as DETAILS
                ) AS new
                ON (EAP.ID_ACTIVITE_PRAXEDO = new.ID_ACTIVITE_PRAXEDO AND EAP.ID_AGENT = new.ID_AGENT)
                WHEN MATCHED THEN
                    UPDATE SET 
                        TYPE_ACTIVITE = new.TYPE_ACTIVITE,
                        DATE_UPDATE = new.DATE_UPDATE,
                        DATE_DEBUT = new.DATE_DEBUT,
                        DATE_UPDATE_PRAXEDO = new.DATE_UPDATE_PRAXEDO,
                        DATE_FIN = new.DATE_FIN,
                        DETAILS = new.DETAILS
                WHEN NOT MATCHED THEN
                    INSERT (TYPE_ACTIVITE, ID_AGENT, DATE_DEBUT, DATE_FIN, DATE_UPDATE, DATE_UPDATE_PRAXEDO, INTER_PRAXEDO, ID_ACTIVITE_PRAXEDO, DETAILS)
                    VALUES (?, ?, CONVERT(DATETIME, ?, 121), CONVERT(DATETIME, ?, 121), CONVERT(DATETIME, ?, 121), CONVERT(DATETIME, ?, 121), ?, ?, ?);';

            $params = [
                // Pour le USING SELECT
                $activity['TYPE_ACTIVITE'],
                $activity['ID_AGENT'],
                $dateDebut->format('Y-m-d H:i:s.v'),
                $dateFin->format('Y-m-d H:i:s.v'),
                $dateUpdate->format('Y-m-d H:i:s.v'),
                $dateUpdatePraxedo->format('Y-m-d H:i:s.v'),
                $activity['INTER_PRAXEDO'] ?? '',
                $activity['ID_ACTIVITE_PRAXEDO'],
                $activity['DETAILS'] ?? '',
                // Pour l'INSERT VALUES
                $activity['TYPE_ACTIVITE'],
                $activity['ID_AGENT'],
                $dateDebut->format('Y-m-d H:i:s.v'),
                $dateFin->format('Y-m-d H:i:s.v'),
                $dateUpdate->format('Y-m-d H:i:s.v'),
                $dateUpdatePraxedo->format('Y-m-d H:i:s.v'),
                $activity['INTER_PRAXEDO'] ?? '',
                $activity['ID_ACTIVITE_PRAXEDO'],
                $activity['DETAILS'] ?? '',
            ];

            $stmt = $this->wavesoft->query($sql);
            $result = $stmt->execute($params);

            $this->logger->info('Activité mise à jour dans Wavesoft', [
                'id_activite' => $activity['ID_ACTIVITE_PRAXEDO'],
                'agent' => $activity['ID_AGENT'],
                'dates' => [
                    'debut' => $dateDebut->format('Y-m-d H:i:s.v'),
                    'fin' => $dateFin->format('Y-m-d H:i:s.v'),
                    'update' => $dateUpdate->format('Y-m-d H:i:s.v'),
                    'update_praxedo' => $dateUpdatePraxedo->format('Y-m-d H:i:s.v'),
                ],
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour de l\'activité dans Wavesoft', [
                'error' => $e->getMessage(),
                'activity' => $activity,
            ]);
            throw $e;
        }
    }

    /**
     * Supprime une activité de Wavesoft.
     *
     * @throws Exception
     */
    public function deleteActivity(string $idActivityPraxedo): bool
    {
        try {
            $sql = 'DELETE FROM EXT_ACTIVITE_PRAXEDO WHERE ID_ACTIVITE_PRAXEDO = ?';
            $stmt = $this->wavesoft->query($sql);
            $result = $stmt->execute([$idActivityPraxedo]);

            $this->logger->info('Activité supprimée de Wavesoft', [
                'id_activite' => $idActivityPraxedo,
            ]);

            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la suppression de l\'activité dans Wavesoft', [
                'error' => $e->getMessage(),
                'id_activite' => $idActivityPraxedo,
            ]);
            throw $e;
        }
    }
}

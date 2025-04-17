<?php

namespace App\Applications\Wavesoft\scripts\clients\Service;

use App\Applications\Wavesoft\WavesoftClient;
use Exception;
use Psr\Log\LoggerInterface;

readonly class WavesoftClientsService
{
    public function __construct(
        private LoggerInterface $logger,
        private WavesoftClient  $wavesoftClient,
    ) {
    }

    /**
     * Récupère les clients Wavesoft mis à jour depuis une date donnée
     *
     * @param string $dateUpdateClients Date de dernière mise à jour au format Y-m-d H:i:s
     * @return array Tableau des clients mis à jour
     * @throws Exception
     */
    public function getUpdatedClients(string $dateUpdateClients): array
    {
        $this->logger->info('Récupération des clients Wavesoft mis à jour depuis', [
            'date' => $dateUpdateClients
        ]);

        try {
            $sql = "SELECT 
                        vlc.TIRCODE AS Code,
                        CASE
                            WHEN vlc.TIRSOCIETETYPE is not null THEN CONCAT(vlc.TIRSOCIETETYPE, ' ', vlc.TIRSOCIETE)
                            ELSE vlc.TIRSOCIETE
                        END AS Nom,
                        CASE
                            WHEN vlc.ADRCONTACTNOM is not null and vlc.ADRCONTACTPRENOM is not null then CONCAT(vlc.ADRCONTACTNOM, ' ', vlc.ADRCONTACTPRENOM)
                            WHEN vlc.ADRCONTACTNOM is not null and vlc.ADRCONTACTPRENOM is null then vlc.ADRCONTACTNOM
                            WHEN vlc.ADRCONTACTNOM is null and vlc.ADRCONTACTPRENOM is not null then vlc.ADRCONTACTPRENOM
                            ELSE ' '
                        END AS Contact,
                        CASE
                            WHEN vlc.ADRL1 is not null AND vlc.ADRL2 is not null AND vlc.ADRL3 is not null THEN CONCAT(vlc.ADRL1, ' ', vlc.ADRL2, ' ', vlc.ADRL3)
                            WHEN vlc.ADRL1 is not null AND vlc.ADRL2 is not null AND vlc.ADRL3 is null THEN CONCAT(vlc.ADRL1, ' ', vlc.ADRL2)
                            WHEN vlc.ADRL1 is not null AND vlc.ADRL2 is null AND vlc.ADRL3 is null THEN vlc.ADRL1
                            ELSE ' '
                        END AS Adresse,
                        vlc.ADRCODEPOSTAL AS CodePostal, 
                        vlc.ADRVILLE AS Ville, 
                        vlc.ADRPAYS AS Pays, 
                        vlc.ADRPORTABLE AS Portable, 
                        vlc.ADRMAIL as Email
                    FROM V_LST_CLIENTS as vlc
                    WHERE vlc.TIRISACTIF = 'O' 
                        AND vlc.DATEUPDATE >= CAST(? AS DATETIME2) 
                        AND (vlc.ADRVILLE is not null OR vlc.ADRVILLE <> '')
                    ORDER BY vlc.TIRCODE";
                
            $stmt = $this->wavesoftClient->query($sql);
            $stmt->execute([$dateUpdateClients]);
            $clients = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->logger->info('Clients Wavesoft récupérés avec succès', [
                'count' => count($clients)
            ]);
            
            return $clients;
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la récupération des clients Wavesoft', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
} 
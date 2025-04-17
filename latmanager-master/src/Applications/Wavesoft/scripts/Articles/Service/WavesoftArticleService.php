<?php

namespace App\Applications\Wavesoft\scripts\Articles\Service;

use App\Applications\Wavesoft\WavesoftClient;
use Doctrine\DBAL\Connection;
use Exception;
use Psr\Log\LoggerInterface;

readonly class WavesoftArticleService
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private WavesoftClient $wavesoft,
    ) {
    }

    /**
     * Récupère uniquement les articles actifs depuis WaveSoft.
     *
     * @return array Liste des articles actifs
     *
     * @throws Exception
     */
    public function getAllArticles(): array
    {
        try {
            $startTime = microtime(true);

            $sql = <<<SQL
                SELECT A.ARTCODE, AFM.AFMCODE, AFM.AFMINTITULE, A.ARTDESIGNATION, A.ARTISACTIF, ATL.ATFPRIX
                FROM ARTICLES A WITH (NOLOCK)
                LEFT JOIN ARTTARIF AT WITH (NOLOCK) ON AT.ARTID = A.ARTID 
                LEFT JOIN TARIFS T WITH (NOLOCK) ON T.TRFID = AT.TRFID 
                LEFT JOIN ARTTARIFLIGNE ATL WITH (NOLOCK) ON ATL.ARTID = AT.ARTID AND ATL.TRFID = AT.TRFID AND ATL.ATFQTE = A.ARTQTEVENTEMINI
                LEFT JOIN ARTFAMILLES AFM WITH (NOLOCK) ON AFM.AFMID = A.AFMID
                WHERE T.TRFID = 1
                OPTION (RECOMPILE)
            SQL;

            $stmt = $this->wavesoft->query($sql);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la récupération des articles WaveSoft', [
                'error' => $e->getMessage(),
                'temps' => round(microtime(true) - $startTime, 3) . 's'
            ]);
            throw $e;
        }
    }
}

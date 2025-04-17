<?php

namespace App\ODF\Infrastructure\Repository;

use App\ODF\Domain\Repository\PieceDetailsRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Tests\Model;

readonly class DbalPieceDetailsRepository implements PieceDetailsRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @throws Exception
     */
    public function findByPcdid(int $pcdid): array
    {
        $sql = "SELECT 
            pd.PCDID, 
            pd.PCDNUM, 
            pd.DEPID_IN, 
            pd.DEPID_OUT, 
            pd.MEMOID,
            pd.AFFID,
            pdl.PLDTYPE,
            pdl.PLDQTE,
            CASE 
                WHEN pdl.PLDTYPE = 'C' 
                THEN (select PLDDIVERS from PIECEDIVERSLIGNES where PIECEDIVERSLIGNES.PLDSTOTID = pdl.PLDPEREID and PIECEDIVERSLIGNES.PCDID = pdl.PCDID) 
                ELSE pdl.PLDDIVERS 
            END as PLDDIVERS,
            pdl.ARTID,
            pdl.PLDSTOTID,
            pdl.PLDPEREID,
            pdl.PLDNUMLIGNE,
            a.ARTCODE,
            a.ARTDESIGNATION,
            a.ARTCUMP,
            pdlp.DATEDEBUT,
            pdlp.DATEFIN,
            pro.PROCODE,
            ad.ARDSTOCKREEL,
            ema.MFGMODELE,
            (COALESCE(ad.ARDSTOCKREEL, 0) - pdl.PLDQTE) as QTE_RESTANTE_COMMANDE_GLOBALE,
            (SELECT parent_pro.PROCODE 
             FROM PIECEDIVERSLIGNES parent_pdl 
             LEFT JOIN PRODUITS parent_pro ON parent_pdl.PROID = parent_pro.PROID 
             WHERE parent_pdl.PLDSTOTID = pdl.PLDPEREID 
             AND parent_pdl.PLDTYPE = 'L' 
             AND parent_pdl.PCDID = pd.PCDID) as PROCODE_PARENT
        FROM 
            PIECEDIVERS pd
        INNER JOIN 
            PIECEDIVERSLIGNES pdl ON pd.PCDID = pdl.PCDID
        LEFT JOIN 
            EXT_MODELE_API ema ON
            CASE 
                WHEN pdl.PLDTYPE = 'C' 
                THEN (select PLDDIVERS from PIECEDIVERSLIGNES where PIECEDIVERSLIGNES.PLDSTOTID = pdl.PLDPEREID and PIECEDIVERSLIGNES.PCDID = pdl.PCDID) 
                ELSE pdl.PLDDIVERS 
            END  
            = ema.SERIALNUMBER
        LEFT JOIN
            PRODUITS pro ON pdl.PROID = pro.PROID
        LEFT JOIN 
            ARTDEPOT ad ON ad.DEPID = pd.DEPID_OUT AND ad.ARTID = pdl.ARTID
        LEFT JOIN 
            MEMOS m ON m.MEMOID = pd.MEMOID
        LEFT JOIN 
            ARTICLES a ON a.ARTID = pdl.ARTID
        LEFT JOIN 
            PIECEDIVERSLIGNES_P pdlp ON pd.PCDID = pdlp.PCDID AND pdl.PLDNUMLIGNE = pdlp.PLDNUMLIGNE
        WHERE 
            pd.PCDID = :pcdid";

        return $this->connection->executeQuery($sql, ['pcdid' => $pcdid])
            ->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function findByBtrNumber(string $btrNumber): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $result = $qb
            ->select('COUNT(p.PCDID)')
            ->from('PIECEDIVERS'/** @type MODEL */, 'p')
            ->where('p.PCDNUM = :btrNumber')
            ->setParameter('btrNumber', $btrNumber)
            ->executeQuery()
            ->fetchOne();

        return (bool) $result;
    }

    /**
     * @throws Exception
     */
    public function exists(int $pcdid): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $result = $qb
            ->select('COUNT(p.PCDID)')
            ->from('PIECEDIVERS'/** @type MODEL */, 'p')
            ->where('p.PCDID = :pcdid')
            ->setParameter('pcdid', $pcdid)
            ->executeQuery()
            ->fetchOne();

        return (bool) $result;
    }

    /**
     * @throws Exception
     */
    public function findUniqueIdByPcdid(int $pcdid): ?array
    {
        $sql = "SELECT pd.PCDISCLOS, p.UNIQUEID, pd.MEMOID, pd.PCDNUM
                FROM PIECEDIVERS pd
                LEFT JOIN PIECEDIVERS_P p ON p.PCDID = pd.PCDID 
                LEFT JOIN MEMOS m ON m.MEMOID = pd.MEMOID 
                WHERE pd.PCDID = :pcdid";

        $result = $this->connection->executeQuery($sql, ['pcdid' => $pcdid])
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @throws Exception
     */
    public function updateUniqueId(int $pcdid, string $uniqueId): void
    {
        $this->connection->executeStatement(
            "UPDATE PIECEDIVERS_P SET UNIQUEID = :uniqueId WHERE PCDID = :pcdid",
            [
                'uniqueId' => $uniqueId,
                'pcdid' => $pcdid
            ]
        );
    }

    /**
     * Récupère les articles parents avec leurs numéros de série pour un numéro de pièce donné
     * 
     * @param string $pcdnum Numéro de la pièce
     * @return array Liste des articles parents avec leurs numéros de série
     * @throws Exception
     */
    public function getArticleParent(string $pcdnum): array
    {
        $sql = "SELECT DISTINCT art.ARTCODE, pdl.PLDDIVERS
                FROM PIECEDIVERS pd
                INNER JOIN PIECEDIVERSLIGNES pdl ON pd.PCDID = pdl.PCDID
                LEFT JOIN ARTICLES art ON pdl.ARTID = art.ARTID
                WHERE pd.PCDNUM = :pcdnum
                AND pdl.PLDTYPE = 'L'";

        return $this->connection->executeQuery($sql, ['pcdnum' => $pcdnum])
            ->fetchAllAssociative();
    }

    /**
     * Récupère un numéro de série pour un coupon
     * 
     * @param int $artId ID de l'article
     * @param string $pcdNum Numéro de pièce
     * @param array $usedSerialNumbers Numéros de série déjà utilisés
     * @return string|null Numéro de série ou null si non trouvé
     */
    public function getNumberSerieCoupon(int $artId, string $pcdNum, array $usedSerialNumbers = []): ?string
    {
        try {
            $queryBuilder = $this->connection->createQueryBuilder();
            $queryBuilder
                ->select('OPENUMSERIE')
                ->from('OPERATIONSTOCK')
                ->where('ARTID = :artid')
                ->andWhere('OPEREFPIECE LIKE :pcdNum')
                ->setParameter('artid', $artId)
                ->setParameter('pcdNum', $pcdNum . '%');
            
            if (!empty($usedSerialNumbers)) {
                $queryBuilder->andWhere('OPENUMSERIE NOT IN (:serials)')
                    ->setParameter('serials', $usedSerialNumbers, Connection::PARAM_STR_ARRAY);
            }
            
            $result = $queryBuilder->executeQuery()->fetchAssociative();
            
            return $result ? $result['OPENUMSERIE'] : null;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération du numéro de série du coupon', [
                'error' => $e->getMessage(),
                'artId' => $artId,
                'pcdNum' => $pcdNum
            ]);
            
            return null;
        }
    }

    /**
     * Récupère le CUMP d'un coupon
     * 
     * @param int $artId ID de l'article
     * @param string $serialNumber Numéro de série
     * @return float|null CUMP du coupon ou null si non trouvé
     */
    public function getCouponCump(int $artId, string $serialNumber): ?float
    {
        try {
            $queryBuilder = $this->connection->createQueryBuilder();
            $queryBuilder
                ->select('OPECUMP')
                ->from('OPERATIONSTOCK')
                ->where('ARTID = :artid')
                ->andWhere('OPENUMSERIE = :serialNumber')
                ->setParameter('artid', $artId)
                ->setParameter('serialNumber', $serialNumber);
            
            $result = $queryBuilder->executeQuery()->fetchAssociative();
            
            return $result ? (float)$result['OPECUMP'] : null;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération du CUMP du coupon', [
                'error' => $e->getMessage(),
                'artId' => $artId,
                'serialNumber' => $serialNumber
            ]);
            
            return null;
        }
    }

    /**
     * Met à jour le statut d'une pièce
     * 
     * @param int $pcdid ID de la pièce
     * @param string $status Nouveau statut
     * @return void
     */
    public function updatePieceStatus(int $pcdid, string $status): void
    {
        try {
            $this->connection->update(
                'PIECE',
                ['PCDDIVERS' => $status],
                ['PCDID' => $pcdid]
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour du statut de la pièce', [
                'error' => $e->getMessage(),
                'pcdid' => $pcdid,
                'status' => $status
            ]);
        }
    }

    public function CheckLastEnterStockDate(string $numSerie, string $artId): array
    {
        try {
            $queryBuilder = $this->connection->createQueryBuilder();
            $queryBuilder
                ->select('OPEID, OPEDATE, OPENUMSERIE, OPESENS, OPENATURESTOCK, OPEREFPIECE')
                ->from('OPERATIONSTOCK')
                ->where('OPENUMSERIE LIKE :numSerie')
                ->andWhere('OPESENS = 1')
                ->andWhere('OPENATURESTOCK = :natureStock')
                ->andWhere('ARTID = :artId')
                ->orderBy('OPEDATE', 'DESC')
                ->setMaxResults(1)
                ->setParameter('numSerie', $numSerie)
                ->setParameter('artId', $artId)
                ->setParameter('natureStock', 'R');

            $result = $queryBuilder->executeQuery()->fetchAssociative();

            return $result ?: [];
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération de la dernière date d\'entrée en stock', [
                'error' => $e->getMessage(),
                'numSerie' => $numSerie
            ]);

            return [];
        }
    }

    /**
     * Sauvegarde un numéro de série et son modèle associé
     *
     * @param string $serialNumber Numéro de série
     * @param string $model Modèle du fabricant
     * @return void
     * @throws Exception
     */
    public function saveSerialNumberAndModel(string $serialNumber, string $model): void
    {
        try {
            $queryBuilder = $this->connection->createQueryBuilder();
            
            $queryBuilder
                ->insert('EXT_MODELE_API')
                ->values([
                    'SERIALNUMBER' => ':serialNumber',
                    'MFGMODELE' => ':model',
                    'DATEUPDATE' => 'GETDATE()'
                ])
                ->setParameters([
                    'serialNumber' => $serialNumber,
                    'model' => $model
                ]);
            
            $queryBuilder->executeStatement();
            
            $this->logger->info('Numéro de série et modèle sauvegardés', [
                'serialNumber' => $serialNumber,
                'model' => $model
            ]);
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la sauvegarde du numéro de série et du modèle', [
                'serialNumber' => $serialNumber,
                'model' => $model,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function getQuantityCheck(int $pcdid): array
    {
        $sql = "SELECT 
            a.ARTCODE,
            sum(pdl.PLDQTE) SUMPLDQTE,
            ad.ARDSTOCKREEL,
            case when ad.ARDSTOCKREEL - sum(pdl.PLDQTE) > 0 then ad.ARDSTOCKREEL - sum(pdl.PLDQTE) else 0 end  as RESTANT,
            case when ad.ARDSTOCKREEL - sum(pdl.PLDQTE) > 0 then 0 else sum(pdl.PLDQTE) - ad.ARDSTOCKREEL end  as MANQUANT 
            FROM 
                PIECEDIVERS pd
            INNER JOIN 
                PIECEDIVERSLIGNES pdl ON pd.PCDID = pdl.PCDID
            LEFT JOIN 
                ARTDEPOT ad ON ad.DEPID = pd.DEPID_OUT AND ad.ARTID = pdl.ARTID
            LEFT JOIN 
                ARTICLES a ON a.ARTID = pdl.ARTID
            WHERE 
                pd.PCDID = :pcdid AND pdl.PLDTYPE ='C'
            GROUP BY
                a.ARTCODE
                ,Ad.ARDSTOCKREEL
                ,pd.DEPID_OUT";

           return $this->connection->executeQuery($sql, ['pcdid' => $pcdid])->fetchAllAssociative();
    }
}

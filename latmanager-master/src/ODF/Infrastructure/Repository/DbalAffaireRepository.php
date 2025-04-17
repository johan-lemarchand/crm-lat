<?php

namespace App\ODF\Infrastructure\Repository;

use App\ODF\Domain\Repository\AffaireRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use PDO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Tests\Model;

readonly class DbalAffaireRepository implements AffaireRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection
    ) {}

    public function findByPcdid(int $pcdid): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();
            
            return $qb
                ->select('a.*')
                ->from('AFFAIRES', 'a')
                ->innerJoin('a', 'PIECE_DIVERS', 'pd', 'pd.AFFID = a.AFFID')
                ->where('pd.PCDID = :pcdid')
                ->setParameter('pcdid', $pcdid)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception $e) {
            throw new \RuntimeException('Erreur lors de la récupération de l\'affaire: ' . $e->getMessage());
        }
    }

    public function findByAffaireId(int $pcdid): array
    {
        try {
            // D'abord on récupère les infos de la pièce
            $qb = $this->connection->createQueryBuilder();
            $result = $qb->select('p.PCDID', 'p.PCDNUM', 'p.AFFID', 'p.PROCODE', 'p.PROCODE_PARENT')
                ->from('PIECES', 'p')
                ->where('p.PCDID = :pcdid')
                ->setParameter('pcdid', $pcdid)
                ->executeQuery()
                ->fetchAssociative();

            if (!$result) {
                return [
                    'status' => 'error',
                    'messages' => [[
                        'message' => 'Pièce non trouvée',
                        'status' => 'error'
                    ]],
                    'affaire' => null
                ];
            }

            // Si une affaire est déjà associée, on récupère son code
            if (!empty($result['AFFID'])) {
                $qb = $this->connection->createQueryBuilder();
                $affaire = $qb->select('a.AFFCODE')
                    ->from('AFFAIRES'/** @type MODEL */, 'a')
                    ->where('a.AFFID = :affid')
                    ->setParameter('affid', $result['AFFID'])
                    ->executeQuery()
                    ->fetchAssociative();

                return [
                    'status' => 'success',
                    'messages' => [],
                    'affaire' => [
                        'code' => $affaire['AFFCODE'],
                        'intitule' => $result['PROCODE_PARENT'] ?? $result['PROCODE']
                    ]
                ];
            }

            // Si pas d'affaire associée
            return [
                'status' => 'success',
                'messages' => [],
                'affaire' => [
                    'code' => $result['PROCODE'],
                    'intitule' => $result['PROCODE_PARENT'] ?? $result['PROCODE']
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'messages' => [[
                    'message' => 'Erreur lors de la récupération de l\'affaire: ' . $e->getMessage(),
                    'status' => 'error'
                ]],
                'affaire' => null
            ];
        }
    }

    /**
     * @throws Exception
     */
    public function findByAffaireIdWithDetails(int $affaireId): array|false
    {
        $qb = $this->connection->createQueryBuilder();
        return $qb
            ->select('a.*')
            ->from('AFFAIRES', 'a')
            ->where('a.AFFID = :affaireId')
            ->setParameter('affaireId', $affaireId, Types::INTEGER)
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * @throws Exception
     */
    public function findAffaireByPcdid(int $pcdid): ?array
    {
        $qb = $this->connection->createQueryBuilder();
        
        $result = $qb
            ->select('p.AFFID', 'p.PCDNUM')
            ->from('PIECEDIVERS'/** @type MODEL */, 'p')
            ->where('p.PCDID = :pcdid')
            ->setParameter('pcdid', $pcdid)
            ->executeQuery()
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @throws Exception
     */
    public function findAffaireByCode(string $affcode): ?array
    {
        $qb = $this->connection->createQueryBuilder();
        
        $result = $qb
            ->select('a.AFFID')
            ->from('AFFAIRES'/** @type MODEL */, 'a')
            ->where('a.AFFCODE = :affcode')
            ->setParameter('affcode', $affcode)
            ->executeQuery()
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * @throws \Exception
     */
    public function createAffaire(string $affcode, string $affIntitule): int
    {
        try {
            $pdo = new \PDO($_ENV['DATABASE_DSN'], $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = "DECLARE @newID int;
                    DECLARE @table varchar(50);
                    SET @table = 'AFFAIRES';
                    EXEC @newID = ws_sp_GetIdTable @Table;
                    SELECT @newID as new_id;";
            $stmt = $pdo->query($sql);
            $stmt->nextRowset();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $affid = $result['new_id'];

            // Insérer la nouvelle affaire
            $this->connection->executeStatement(
                "INSERT INTO AFFAIRES (AFFID, AFFCODE, AFFINTITULE, AFFISACTIF, MODID)
                 VALUES (?, ?, ?, 'O', 408)",
                [$affid, $affcode, $affIntitule]
            );

            return $affid;

        } catch (Exception $e) {
            throw new \RuntimeException('Erreur lors de la création de l\'affaire: ' . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function updateAffaireLinks(int $pcdid, int $affid): void
    {
        // Mise à jour de la pièce
        $qb = $this->connection->createQueryBuilder();
        $qb->update('PIECEDIVERS')
            ->set('AFFID', ':affid')
            ->where('PCDID = :pcdid')
            ->setParameters([
                'affid' => $affid,
                'pcdid' => $pcdid
            ])
            ->executeStatement();

        // Mise à jour des lignes
        $qb = $this->connection->createQueryBuilder();
        $qb->update('PIECEDIVERSLIGNES')
            ->set('AFFID', ':affid')
            ->where('PCDID = :pcdid')
            ->andWhere('PLDTYPE IN (\'L\', \'C\')')
            ->setParameters([
                'affid' => $affid,
                'pcdid' => $pcdid
            ])
            ->executeStatement();
    }
}

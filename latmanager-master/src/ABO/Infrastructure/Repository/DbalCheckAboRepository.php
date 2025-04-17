<?php

namespace App\ABO\Infrastructure\Repository;

use App\ABO\Domain\Interface\CheckAboRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Tests\Model;

readonly class DbalCheckAboRepository implements CheckAboRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection
    ) {}

    public function findByPcvnum(string $pcvnum): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();
                return $qb
                    ->select(
                    'PV.PCVID',
                    'PV.PCVNUM',
                    'T.TIRID',
                    'T.TIRCODE',
                    'T.TIRSOCIETETYPE',
                    'T.TIRSOCIETE',
                    'PV.MEMOID',
                    'PL.ARTID',
                    'A.ARTCODE',
                    'A.ARTDESIGNATION',
                    'AP.ARTISABO',
                    'AP.ARTISCODESN',
                    '(SELECT V_EXT_LSTABONNEMENT.PASN_NUM FROM V_EXT_LSTABONNEMENT WHERE PL.PLVID = V_EXT_LSTABONNEMENT.PLVID) AS PASN_NUM',
                    '(SELECT V_EXT_LSTABONNEMENT.ETAT_ABO FROM V_EXT_LSTABONNEMENT WHERE PL.PLVID = V_EXT_LSTABONNEMENT.PLVID) AS ETAT_ABO',
                    'AF.AFFCODE',
                    'PV.PCVNUMEXT',
                    'PV.PCVISHT',
                    'PV.PCVDATEEFFET',
                    'TRF.TRFCODE',
                    'DEV.DEVSYMBOLE',
                    'DEP.DEPCODE',
                    'TR.TIRCODE AS CODECOM',
                    '(SELECT TIRCODE FROM TIERS WHERE PV.SOCID = TIERS.TIRID) AS CODEETAB',
                    'PV.TYNCODE',
                    'PV.PCVDATELIVRAISON',
                    'PL.PLVDIVERS',
                    'PL.PLVNUMSERIE',
                    'PL.PLVQTE',
                    'PL.PLVISIMPRIMABLE',
                    'PL.PLVDESIGNATION',
                    'PL.PLVPUBRUT',
                    'PL.PLVREMISE_MNT',
                    'PL.PLVPUNET',
                    'PL.PLVDATE',
                    'PL.PLVNUMLOT',
                    'PL.PLVNUMSERIE',
                    'PL.TVACODE',
                    'PL.TPFCODE',
                    'CPT.CPTCODE',
                    'PL.PLVSTYLEISGRAS',
                    'PL.PLVSTYLEISITALIC',
                    'PL.PLVSTYLEISIMPPARTIEL',
                    'PL.PLVSTYLEISSOULIGNE',
                    'PL.PLVLASTPA',
                    'PL.TPFCODE1',
                    'PL.TPFCODE2',
                    'PL.TPFCODE3',
                    'PL.TPFCODE4',
                    'PL.TPFCODE5',
                    'PL.TPFCODE6',
                    'PL.TPFCODE7',
                    'PL.TPFCODE8',
                    'PL.TPFCODE9',
                    'PL.PLVD1',
                    'PL.PLVD2',
                    'PL.PLVD3',
                    'PL.PLVD4',
                    'PL.PLVD5',
                    'PL.PLVD6',
                    'PL.PLVD7',
                    'PL.PLVD8',
                    'PL.PLVFEFOPEREMPTION',
                    'ANA.ANSCODE',
                    'PL.PLVQTETRANSFO',
                    'PL.PLVSTYLETAILLE',
                    'PL.PLVSTYLECOULEUR',
                    'PL.PLVSTYLEISBARRE',
                    'PLP.QRCODE',
                    'PLP.CODESN',
                    'PLP.DATEDEBUT',
                    'PLP.DATEFIN',
                    'TF.TIRCODE AS F_TIRCODE',
                    'TF.TIRSOCIETETYPE AS F_TIRSOCIETETYPE',
                    'TF.TIRSOCIETE AS F_TIRSOCIETE',
                    'TL.TIRCODE AS L_TIRCODE',
                    'TL.TIRSOCIETETYPE AS L_TIRSOCIETETYPE',
                    'TL.TIRSOCIETE AS L_TIRSOCIETE',
                    'TC.TIRCODE AS C_TIRCODE',
                    'TC.TIRSOCIETETYPE AS C_TIRSOCIETETYPE',
                    'TC.TIRSOCIETE AS C_TIRSOCIETE',
                    'ADRCF.ADRCONTACTNOM AS CF_CONTACT_NOM',
                    'ADRCF.ADRCONTACTTYPE AS CF_CONTACT_TYPE',
                    'ADRCF.ADRCONTACTPRENOM AS CF_CONTACT_PRENOM',
                    'ADRCF.ADRL1 AS CF_ADRL1',
                    'ADRCF.ADRL2 AS CF_ADRL2',
                    'ADRCF.ADRL3 AS CF_ADRL3',
                    'ADRCF.ADRCODEPOSTAL AS CF_ADRCODEPOSTAL',
                    'ADRCF.ADRVILLE AS CF_ADRVILLE',
                    'ADRCF.ADRPAYS AS CF_ADRPAYS',
                    'ADRCF.ADRTEL AS CF_ADRTEL',
                    'ADRCF.ADRPORTABLE AS CF_ADRPORTABLE',
                    'ADRCF.ADRMAIL AS CF_ADRMAIL',
                    'ADRCF.ADRNUMTVA AS CF_ADRNUMTVA',
                    'ADRCF.ADRAPE AS CF_ADRAPE',
                    'ADRCF.ADRSIRET AS CF_ADRSIRET',
                    'ADRCF.ADRRCS AS CF_ADRRCS',
                    'ADRCF.ADRSERVICE AS CF_ADRSERVICE',
                    'ADRCF.ADRDEPARTEMENT AS CF_ADRDEPARTEMENT',
                    'ADRF.ADRNUMTVA AS F_ADRNUMTVA',
                    'ADRF.ADRAPE AS F_ADRAPE',
                    'ADRF.ADRSIRET AS F_ADRSIRET',
                    'ADRF.ADRRCS AS F_ADRRCS',
                    'ADRF.ADRCONTACTTYPE AS F_CONTACT_TYPE',
                    'ADRF.ADRCONTACTNOM AS F_CONTACT_NOM',
                    'ADRF.ADRCONTACTPRENOM AS F_CONTACT_PRENOM',
                    'ADRF.ADRL1 AS F_ADRL1',
                    'ADRF.ADRL2 AS F_ADRL2',
                    'ADRF.ADRL3 AS F_ADRL3',
                    'ADRF.ADRCODEPOSTAL AS F_ADRCODEPOSTAL',
                    'ADRF.ADRVILLE AS F_ADRVILLE',
                    'ADRF.ADRPAYS AS F_ADRPAYS',
                    'ADRF.ADRTEL AS F_ADRTEL',
                    'ADRF.ADRPORTABLE AS F_ADRPORTABLE',
                    'ADRF.ADRMAIL AS F_ADRMAIL',
                    'ADRL.ADRCONTACTTYPE AS L_CONTACT_TYPE',
                    'ADRL.ADRCONTACTNOM AS L_CONTACT_NOM',
                    'ADRL.ADRCONTACTPRENOM AS L_CONTACT_PRENOM',
                    'ADRL.ADRL1 AS L_ADRL1',
                    'ADRL.ADRL2 AS L_ADRL2',
                    'ADRL.ADRL3 AS L_ADRL3',
                    'ADRL.ADRCODEPOSTAL AS L_ADRCODEPOSTAL',
                    'ADRL.ADRVILLE AS L_ADRVILLE',
                    'ADRL.ADRPAYS AS L_ADRPAYS',
                    'ADRL.ADRTEL AS L_ADRTEL',
                    'ADRL.ADRPORTABLE AS L_ADRPORTABLE',
                    'ADRL.ADRMAIL AS L_ADRMAIL'
                )
                ->from('PIECEVENTES'/** @type MODEL */, 'PV')
                ->leftJoin('PV', 'TIERS', 'T', 'PV.TIRID = T.TIRID')
                ->leftJoin('PV', 'TIERS', 'TF', 'PV.TIRID_FAC = TF.TIRID')
                ->leftJoin('PV', 'TIERS', 'TL', 'PV.TIRID_LIV = TL.TIRID')
                ->leftJoin('PV', 'TIERS', 'TC', 'PV.TIRID_CPT = TC.TIRID')
                ->leftJoin('PV', 'TIERS', 'TR', 'PV.TIRID_REP = TR.TIRID')
                ->leftJoin('PV', 'PIECEVENTELIGNES', 'PL', 'PV.PCVID = PL.PCVID')
                ->leftJoin('PV', 'TIERS', 'TCF', 'PV.TIRID = TCF.ADRID')
                ->leftJoin('T', 'ADRESSES', 'ADRCF', 'T.ADRID = ADRCF.ADRID')
                ->leftJoin('PV', 'ADRESSES', 'ADRF', 'PV.ADRID_FAC = ADRF.ADRID')
                ->leftJoin('PV', 'ADRESSES', 'ADRL', 'PV.ADRID_LIV = ADRL.ADRID')
                ->leftJoin('PV', 'AFFAIRES', 'AF', 'PV.AFFID = AF.AFFID')
                ->leftJoin('PV', 'TARIFS', 'TRF', 'PV.TRFID = TRF.TRFID')
                ->leftJoin('PV', 'DEVISES', 'DEV', 'PV.DEVID = DEV.DEVID')
                ->leftJoin('PV', 'DEPOTS', 'DEP', 'PV.DEPID = DEP.DEPID')
                ->leftJoin('PL', 'PIECEVENTELIGNES_P', 'PLP', 'PL.PLVID = PLP.PLVID')
                ->leftJoin('PL', 'COMPTES', 'CPT', 'PL.DEPID = CPT.CPTID')
                ->leftJoin('PL', 'ANASECTIONS', 'ANA', 'PL.ANSID = ANA.ANSID')
                ->leftJoin('PL', 'ARTICLES', 'A', 'PL.ARTID = A.ARTID')
                ->leftJoin('PL', 'ARTICLES_P', 'AP', 'PL.ARTID = AP.ARTID')
                ->where('PV.PCVNUM = :pcvnum')
                ->andWhere('AP.ARTISABO = :artisabo')
                ->orderBy('A.ARTCODE', 'ASC')
                ->addOrderBy('PL.PLVNUMSERIE', 'ASC')
                ->addOrderBy('PLP.DATEDEBUT', 'ASC')
                ->setParameter('pcvnum', $pcvnum)
                ->setParameter('artisabo', 'O')
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception $e) {
            throw new \RuntimeException('Erreur lors de la récupération du BLC: ' . $e->getMessage());
        }
    }

    public function findAdrNewCodeFinalClient(string $newCodeClient): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();
                return $qb
                    ->select(
                    'T.TIRID',
                    'T.TIRCODE',
                    'T.TIRSOCIETETYPE',
                    'T.TIRSOCIETE',
                    'TF.TIRCODE AS F_TIRCODE',
                    'TF.TIRSOCIETETYPE AS F_TIRSOCIETETYPE',
                    'TF.TIRSOCIETE AS F_TIRSOCIETE',
                    'TL.TIRCODE AS L_TIRCODE',
                    'TL.TIRSOCIETETYPE AS L_TIRSOCIETETYPE',
                    'TL.TIRSOCIETE AS L_TIRSOCIETE',
                    'ADRCF.ADRCONTACTNOM AS CF_CONTACT_NOM',
                    'ADRCF.ADRCONTACTTYPE AS CF_CONTACT_TYPE',
                    'ADRCF.ADRCONTACTPRENOM AS CF_CONTACT_PRENOM',
                    'ADRCF.ADRL1 AS CF_ADRL1',
                    'ADRCF.ADRL2 AS CF_ADRL2',
                    'ADRCF.ADRL3 AS CF_ADRL3',
                    'ADRCF.ADRCODEPOSTAL AS CF_ADRCODEPOSTAL',
                    'ADRCF.ADRVILLE AS CF_ADRVILLE',
                    'ADRCF.ADRPAYS AS CF_ADRPAYS',
                    'ADRCF.ADRTEL AS CF_ADRTEL',
                    'ADRCF.ADRPORTABLE AS CF_ADRPORTABLE',
                    'ADRCF.ADRMAIL AS CF_ADRMAIL',
                    'ADRCF.ADRNUMTVA AS CF_ADRNUMTVA',
                    'ADRCF.ADRAPE AS CF_ADRAPE',
                    'ADRCF.ADRSIRET AS CF_ADRSIRET',
                    'ADRCF.ADRRCS AS CF_ADRRCS',
                    'ADRCF.ADRSERVICE AS CF_ADRSERVICE',
                    'ADRCF.ADRDEPARTEMENT AS CF_ADRDEPARTEMENT',
                    'ADRF.ADRCONTACTTYPE AS F_CONTACT_TYPE',
                    'ADRF.ADRCONTACTNOM AS F_CONTACT_NOM',
                    'ADRF.ADRCONTACTPRENOM AS F_CONTACT_PRENOM',
                    'ADRF.ADRL1 AS F_ADRL1',
                    'ADRF.ADRL2 AS F_ADRL2',
                    'ADRF.ADRL3 AS F_ADRL3',
                    'ADRF.ADRCODEPOSTAL AS F_ADRCODEPOSTAL',
                    'ADRF.ADRVILLE AS F_ADRVILLE',
                    'ADRF.ADRPAYS AS F_ADRPAYS',
                    'ADRF.ADRTEL AS F_ADRTEL',
                    'ADRF.ADRPORTABLE AS F_ADRPORTABLE',
                    'ADRF.ADRMAIL AS F_ADRMAIL',
                    'ADRF.ADRNUMTVA AS F_ADRNUMTVA',
                    'ADRF.ADRAPE AS F_ADRAPE',
                    'ADRF.ADRSIRET AS F_ADRSIRET',
                    'ADRF.ADRRCS AS F_ADRRCS',
                    'ADRL.ADRCONTACTTYPE AS L_CONTACT_TYPE',
                    'ADRL.ADRCONTACTNOM AS L_CONTACT_NOM',
                    'ADRL.ADRCONTACTPRENOM AS L_CONTACT_PRENOM',
                    'ADRL.ADRL1 AS L_ADRL1',
                    'ADRL.ADRL2 AS L_ADRL2',
                    'ADRL.ADRL3 AS L_ADRL3',
                    'ADRL.ADRCODEPOSTAL AS L_ADRCODEPOSTAL',
                    'ADRL.ADRVILLE AS L_ADRVILLE',
                    'ADRL.ADRPAYS AS L_ADRPAYS',
                    'ADRL.ADRTEL AS L_ADRTEL',
                    'ADRL.ADRPORTABLE AS L_ADRPORTABLE',
                    'ADRL.ADRMAIL AS L_ADRMAIL'
                )
                ->from('TIERS', 'T')
                ->leftJoin('T', 'TIERS', 'TF', 
                    'TF.TIRID = CASE WHEN COALESCE(T.TIRID_FAC, \'\') = \'\' THEN T.TIRID ELSE T.TIRID_FAC END')
                ->leftJoin('TF', 'ADRESSES', 'ADRF', 'ADRF.ADRID = TF.ADRID')
                ->leftJoin('T', 'TIERS', 'TL',
                    'TL.TIRID = CASE WHEN COALESCE(T.TIRID_LIV, \'\') = \'\' THEN T.TIRID ELSE T.TIRID_LIV END')
                ->leftJoin('TL', '(SELECT * FROM TIERS_LIVRAISON WHERE TLVPRINCIPALE = \'O\')', 'TL_PRINCIPALE', 'TL_PRINCIPALE.TIRID = TL.TIRID')
                ->leftJoin('TL', 'ADRESSES', 'ADRL',
                    'ADRL.ADRID = CASE WHEN COALESCE(TL_PRINCIPALE.ADRID, \'\') = \'\' THEN TL.ADRID ELSE TL_PRINCIPALE.ADRID END')
                ->leftJoin('T', 'ADRESSES', 'ADRCF', 'T.ADRID = ADRCF.ADRID')
                ->where('T.TIRID = :newCodeClient')
                ->setParameter('newCodeClient', $newCodeClient)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception $e) {
            throw new \RuntimeException('Erreur lors de la récupération du BLC: ' . $e->getMessage());
        }
    }

    public function findAdrNewCodeClientInvoice(string $newCodeClient): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();
                return $qb
                    ->select(
                    'TF.TIRCODE AS F_TIRCODE',
                    'TF.TIRSOCIETETYPE AS F_TIRSOCIETETYPE',
                    'TF.TIRSOCIETE AS F_TIRSOCIETE',
                    'ADRF.ADRCONTACTTYPE AS F_CONTACT_TYPE',
                    'ADRF.ADRCONTACTNOM AS F_CONTACT_NOM',
                    'ADRF.ADRCONTACTPRENOM AS F_CONTACT_PRENOM',
                    'ADRF.ADRL1 AS F_ADRL1',
                    'ADRF.ADRL2 AS F_ADRL2',
                    'ADRF.ADRL3 AS F_ADRL3',
                    'ADRF.ADRCODEPOSTAL AS F_ADRCODEPOSTAL',
                    'ADRF.ADRVILLE AS F_ADRVILLE',
                    'ADRF.ADRPAYS AS F_ADRPAYS',
                    'ADRF.ADRTEL AS F_ADRTEL',
                    'ADRF.ADRPORTABLE AS F_ADRPORTABLE',
                    'ADRF.ADRMAIL AS F_ADRMAIL',
                    'ADRF.ADRNUMTVA AS F_ADRNUMTVA',
                    'ADRF.ADRAPE AS F_ADRAPE',
                    'ADRF.ADRSIRET AS F_ADRSIRET',
                    'ADRF.ADRRCS AS F_ADRRCS',
                )
                ->from('TIERS', 'TF')
                ->leftJoin('TF', 'ADRESSES', 'ADRF', 'ADRF.ADRID = TF.ADRID')
                ->where('TF.TIRID = :newCodeClient')
                ->setParameter('newCodeClient', $newCodeClient)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception $e) {
            throw new \RuntimeException('Erreur lors de la récupération du BLC: ' . $e->getMessage());
        }
    }

    public function findAdrNewCodeClientDelivery(string $newCodeClient): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();
                return $qb
                    ->select(
                    'TL.TIRCODE AS L_TIRCODE',
                    'TL.TIRSOCIETETYPE AS L_TIRSOCIETETYPE',
                    'TL.TIRSOCIETE AS L_TIRSOCIETE',
                    'ADRL.ADRCONTACTTYPE AS L_CONTACT_TYPE',
                    'ADRL.ADRCONTACTNOM AS L_CONTACT_NOM',
                    'ADRL.ADRCONTACTPRENOM AS L_CONTACT_PRENOM',
                    'ADRL.ADRL1 AS L_ADRL1',
                    'ADRL.ADRL2 AS L_ADRL2',
                    'ADRL.ADRL3 AS L_ADRL3',
                    'ADRL.ADRCODEPOSTAL AS L_ADRCODEPOSTAL',
                    'ADRL.ADRVILLE AS L_ADRVILLE',
                    'ADRL.ADRPAYS AS L_ADRPAYS',
                    'ADRL.ADRTEL AS L_ADRTEL',
                    'ADRL.ADRPORTABLE AS L_ADRPORTABLE',
                    'ADRL.ADRMAIL AS L_ADRMAIL'
                )
                ->from('TIERS', 'TL')
                ->leftJoin('TL', '(SELECT * FROM TIERS_LIVRAISON WHERE TLVPRINCIPALE = \'O\')', 'TL_PRINCIPALE', 'TL_PRINCIPALE.TIRID = TL.TIRID')
                ->leftJoin('TL', 'ADRESSES', 'ADRL',
                    'ADRL.ADRID = CASE WHEN COALESCE(TL_PRINCIPALE.ADRID, \'\') = \'\' THEN TL.ADRID ELSE TL_PRINCIPALE.ADRID END')
                ->where('TL.TIRID = :newCodeClient')
                ->setParameter('newCodeClient', $newCodeClient)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception $e) {
            throw new \RuntimeException('Erreur lors de la récupération du BLC: ' . $e->getMessage());
        }
    }
}

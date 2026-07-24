<?php

namespace App\Repository;

use App\Entity\Avis;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Avis> */
class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

    /** @return Avis[] Les avis laissés par un utilisateur, du plus récent au plus ancien */
    public function findByUtilisateur(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.utilisateur = :u')
            ->setParameter('u', $utilisateur)
            ->orderBy('a.dateAvis', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Avis[] Les avis reçus par un bateau donné (via ses réservations) */
    public function findByBateau(int $bateauId): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.reservation', 'r')
            ->andWhere('r.bateau = :b')
            ->setParameter('b', $bateauId)
            ->orderBy('a.dateAvis', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Moyenne et nombre d'avis pour un ensemble de bateaux, en une seule requête
     * (évite le N+1 sur les listes de bateaux : catalogue, favoris, etc.).
     *
     * @param int[] $bateauIds
     * @return array<int, array{moyenne: float, total: int}> indexé par id de bateau
     */
    public function findAggregateByBateauIds(array $bateauIds): array
    {
        if (!$bateauIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(r.bateau) AS bateauId', 'AVG(a.note) AS moyenne', 'COUNT(a.id) AS total')
            ->join('a.reservation', 'r')
            ->andWhere('r.bateau IN (:ids)')
            ->setParameter('ids', $bateauIds)
            ->groupBy('r.bateau')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['bateauId']] = [
                'moyenne' => round((float) $row['moyenne'], 1),
                'total'   => (int) $row['total'],
            ];
        }

        return $result;
    }
}

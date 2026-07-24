<?php

namespace App\Repository;

use App\Entity\Paiement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Paiement> */
class PaiementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paiement::class);
    }

    /**
     * Somme des paiements enregistrés sur les réservations des bateaux donnés,
     * optionnellement restreinte aux paiements effectués depuis une date.
     *
     * @param int[] $bateauIds
     */
    public function sumMontantPourBateaux(array $bateauIds, ?\DateTimeInterface $depuis = null): string
    {
        if (!$bateauIds) {
            return '0';
        }

        $qb = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.montant), 0) AS total')
            ->join('p.reservation', 'r')
            ->andWhere('r.bateau IN (:bateauIds)')
            ->setParameter('bateauIds', $bateauIds);

        if ($depuis !== null) {
            $qb->andWhere('p.datePaiement >= :depuis')->setParameter('depuis', $depuis);
        }

        return (string) $qb->getQuery()->getSingleScalarResult();
    }

    /** Somme de tous les paiements enregistrés sur la plateforme, optionnellement depuis une date. */
    public function sumMontantTotal(?\DateTimeInterface $depuis = null): string
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.montant), 0) AS total');

        if ($depuis !== null) {
            $qb->andWhere('p.datePaiement >= :depuis')->setParameter('depuis', $depuis);
        }

        return (string) $qb->getQuery()->getSingleScalarResult();
    }
}

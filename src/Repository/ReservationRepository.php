<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Reservation> */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /** @return Reservation[] */
    public function findByUtilisateur(int $utilisateurId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.utilisateur = :id')
            ->setParameter('id', $utilisateurId)
            ->orderBy('r.dateReservation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Reservation[] */
    public function findByBateau(int $bateauId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.bateau = :id')
            ->setParameter('id', $bateauId)
            ->orderBy('r.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Reservation[] Réservations où l'utilisateur est le locataire OU le propriétaire du bateau réservé. */
    public function findForUtilisateurOuProprietaire(int $utilisateurId): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.bateau', 'b')
            ->andWhere('r.utilisateur = :id OR b.proprietaire = :id')
            ->setParameter('id', $utilisateurId)
            ->orderBy('r.dateReservation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Reservation[] Réservations existantes qui chevauchent la période donnée pour ce bateau. */
    public function findOverlapping(int $bateauId, \DateTimeInterface $debut, \DateTimeInterface $fin, ?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.bateau = :bateauId')
            ->andWhere('r.dateDebut < :fin')
            ->andWhere('r.dateFin > :debut')
            ->setParameter('bateauId', $bateauId)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin);

        if ($excludeId !== null) {
            $qb->andWhere('r.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /** Nombre de réservations à venir sur les bateaux d'un propriétaire. */
    public function countAVenirPourProprietaire(int $proprietaireId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.bateau', 'b')
            ->andWhere('b.proprietaire = :prop')
            ->andWhere('r.dateDebut > :maintenant')
            ->setParameter('prop', $proprietaireId)
            ->setParameter('maintenant', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Réservations sur les bateaux donnés qui chevauchent la période — utilisé pour
     * calculer un taux d'occupation (jours réservés / jours disponibles sur la période).
     *
     * @param int[] $bateauIds
     * @return Reservation[]
     */
    public function findChevauchantPeriodePourBateaux(array $bateauIds, \DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        if (!$bateauIds) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->andWhere('r.bateau IN (:bateauIds)')
            ->andWhere('r.dateDebut < :fin')
            ->andWhere('r.dateFin > :debut')
            ->setParameter('bateauIds', $bateauIds)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getResult();
    }
}

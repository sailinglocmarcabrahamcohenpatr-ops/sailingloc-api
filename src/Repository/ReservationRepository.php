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
}

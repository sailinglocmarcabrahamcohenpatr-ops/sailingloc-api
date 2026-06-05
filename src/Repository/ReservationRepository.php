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
}

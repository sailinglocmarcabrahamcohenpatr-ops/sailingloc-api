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
}

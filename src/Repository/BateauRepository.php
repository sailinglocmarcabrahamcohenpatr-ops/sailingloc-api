<?php

namespace App\Repository;

use App\Entity\Bateau;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bateau>
 */
class BateauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bateau::class);
    }

    /**
     * @return Bateau[]
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('b.nomBateau', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Bateau[]
     */
    public function findByProprietaire(int $utilisateurId): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.proprietaire = :id')
            ->setParameter('id', $utilisateurId)
            ->orderBy('b.nomBateau', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Bateau;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
     * Retourne les bateaux paginés avec photos et disponibilités chargés en une seule requête.
     *
     * @return array{items: Bateau[], total: int}
     */
    public function findPaginated(int $page, int $limit, ?string $statut = null, ?int $proprietaireId = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.photos', 'p')->addSelect('p')
            ->leftJoin('b.disponibilites', 'd')->addSelect('d')
            ->orderBy('b.nomBateau', 'ASC');

        if ($statut !== null) {
            $qb->andWhere('b.statut = :statut')->setParameter('statut', $statut);
        }

        if ($proprietaireId !== null) {
            $qb->andWhere('b.proprietaire = :prop')->setParameter('prop', $proprietaireId);
        }

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);
        $paginator->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
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

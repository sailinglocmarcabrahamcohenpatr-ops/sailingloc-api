<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Document> */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /** @return Document[] */
    public function findByUtilisateur(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.utilisateurs', 'u')
            ->where('u = :user')
            ->setParameter('user', $utilisateur)
            ->orderBy('d.dateUpload', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Notification> */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return Notification[] */
    public function findByUser(int $utilisateurId): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.destinataire = :u')
            ->setParameter('u', $utilisateurId)
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

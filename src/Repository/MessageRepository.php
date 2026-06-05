<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Message> */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /** @return Message[] */
    public function findConversation(int $utilisateur1Id, int $utilisateur2Id): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.expediteur = :u1 AND m.destinataire = :u2)')
            ->orWhere('(m.expediteur = :u2 AND m.destinataire = :u1)')
            ->setParameter('u1', $utilisateur1Id)
            ->setParameter('u2', $utilisateur2Id)
            ->orderBy('m.dateEnvoi', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

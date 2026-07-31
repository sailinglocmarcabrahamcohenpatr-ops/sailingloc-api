<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Enum\NotificationTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Mime\Email;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Point d'entrée unique pour notifier un utilisateur : persiste la notification,
 * la pousse en temps réel via Mercure et envoie éventuellement un email — le tout
 * en best-effort pour le push et l'email (une notification reste acquise même si
 * le hub Mercure ou le mailer est indisponible).
 */
class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HubInterface $hub,
        private readonly SerializerInterface $serializer,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {}

    public function notifier(
        Utilisateur $destinataire,
        NotificationTypeEnum $type,
        string $titre,
        string $message,
        ?Reservation $reservation = null,
        ?Email $email = null,
    ): Notification {
        $notification = new Notification();
        $notification->setDestinataire($destinataire);
        $notification->setType($type);
        $notification->setTitre($titre);
        $notification->setMessage($message);
        $notification->setReservation($reservation);

        $this->em->persist($notification);
        $this->em->flush();

        try {
            $payload = $this->serializer->serialize($notification, 'json', ['groups' => ['notification:read']]);
            $this->hub->publish(new Update(
                topics: ["/notifications/user/{$destinataire->getId()}"],
                data: $payload,
                private: true,
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Échec de la publication Mercure pour la notification {id}: {error}', [
                'id' => $notification->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        if ($email !== null) {
            try {
                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Échec de l\'envoi d\'email pour la notification {id}: {error}', [
                    'id' => $notification->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $notification;
    }
}

<?php

namespace App\Entity;

use App\Enum\NotificationTypeEnum;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['notification:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: NotificationTypeEnum::class, length: 30)]
    #[Groups(['notification:read'])]
    private NotificationTypeEnum $type;

    #[ORM\Column(length: 255)]
    #[Groups(['notification:read'])]
    private string $titre;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['notification:read'])]
    private string $message;

    #[ORM\Column]
    #[Groups(['notification:read'])]
    private bool $lu;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_MUTABLE)]
    #[Groups(['notification:read'])]
    private \DateTimeInterface $dateCreation;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'destinataire_id', nullable: false)]
    #[Groups(['notification:read'])]
    private ?Utilisateur $destinataire = null;

    /** Réservation en lien avec la notification (nouvelle réservation, confirmation, avis...). */
    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(name: 'reservation_id', nullable: true, onDelete: 'CASCADE')]
    #[Groups(['notification:read'])]
    private ?Reservation $reservation = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->lu = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): NotificationTypeEnum
    {
        return $this->type;
    }

    public function setType(NotificationTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function isLu(): bool
    {
        return $this->lu;
    }

    public function setLu(bool $lu): static
    {
        $this->lu = $lu;

        return $this;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getDestinataire(): ?Utilisateur
    {
        return $this->destinataire;
    }

    public function setDestinataire(?Utilisateur $destinataire): static
    {
        $this->destinataire = $destinataire;

        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }
}

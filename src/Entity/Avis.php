<?php

namespace App\Entity;

use App\Repository\AvisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvisRepository::class)]
#[ORM\Table(name: 'avis')]
#[ORM\UniqueConstraint(name: 'uq_avis_utilisateur_reservation', columns: ['utilisateur_id', 'reservation_id'])]
class Avis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    #[Groups(['avis:read'])]
    private ?int $id = null;

    /** Moyenne des 3 sous-notes, recalculée via refreshNoteGlobale(). */
    #[ORM\Column]
    #[Groups(['avis:read'])]
    private int $note;

    #[ORM\Column(name: 'note_proprietaire')]
    #[Assert\Range(min: 1, max: 5)]
    #[Groups(['avis:read'])]
    private int $noteProprietaire;

    #[ORM\Column(name: 'note_bateau')]
    #[Assert\Range(min: 1, max: 5)]
    #[Groups(['avis:read'])]
    private int $noteBateau;

    #[ORM\Column(name: 'note_lieu')]
    #[Assert\Range(min: 1, max: 5)]
    #[Groups(['avis:read'])]
    private int $noteLieu;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['avis:read'])]
    private string $commentaire;

    #[ORM\Column(name: 'date_avis', type: Types::DATETIME_MUTABLE)]
    #[Groups(['avis:read'])]
    private \DateTimeInterface $dateAvis;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'avis')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['avis:read'])]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'avis')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['avis:read'])]
    private ?Reservation $reservation = null;

    public function __construct()
    {
        $this->dateAvis = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNote(): int
    {
        return $this->note;
    }

    public function setNote(int $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getNoteProprietaire(): int
    {
        return $this->noteProprietaire;
    }

    public function setNoteProprietaire(int $noteProprietaire): static
    {
        $this->noteProprietaire = $noteProprietaire;

        return $this;
    }

    public function getNoteBateau(): int
    {
        return $this->noteBateau;
    }

    public function setNoteBateau(int $noteBateau): static
    {
        $this->noteBateau = $noteBateau;

        return $this;
    }

    public function getNoteLieu(): int
    {
        return $this->noteLieu;
    }

    public function setNoteLieu(int $noteLieu): static
    {
        $this->noteLieu = $noteLieu;

        return $this;
    }

    /** Recalcule la note globale (moyenne arrondie des 3 sous-notes). À appeler après avoir défini les 3 sous-notes. */
    public function refreshNoteGlobale(): static
    {
        $this->note = (int) round(($this->noteProprietaire + $this->noteBateau + $this->noteLieu) / 3);

        return $this;
    }

    public function getCommentaire(): string
    {
        return $this->commentaire;
    }

    public function setCommentaire(string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getDateAvis(): \DateTimeInterface
    {
        return $this->dateAvis;
    }

    public function setDateAvis(\DateTimeInterface $dateAvis): static
    {
        $this->dateAvis = $dateAvis;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

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

<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['message:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['message:read'])]
    private string $contenu;

    #[ORM\Column]
    #[Groups(['message:read'])]
    private bool $lu;

    #[ORM\Column(name: 'date_envoi', type: Types::DATETIME_MUTABLE)]
    #[Groups(['message:read'])]
    private \DateTimeInterface $dateEnvoi;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'messagesEnvoyes')]
    #[ORM\JoinColumn(name: 'id_utilisateur', nullable: false)]
    #[Groups(['message:read'])]
    private ?Utilisateur $expediteur = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'messagesRecus')]
    #[ORM\JoinColumn(name: 'id_utilisateur_1', nullable: false)]
    #[Groups(['message:read'])]
    private ?Utilisateur $destinataire = null;

    public function __construct()
    {
        $this->dateEnvoi = new \DateTime();
        $this->lu = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

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

    public function getDateEnvoi(): \DateTimeInterface
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(\DateTimeInterface $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;

        return $this;
    }

    public function getExpediteur(): ?Utilisateur
    {
        return $this->expediteur;
    }

    public function setExpediteur(?Utilisateur $expediteur): static
    {
        $this->expediteur = $expediteur;

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
}

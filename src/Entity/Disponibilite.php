<?php

namespace App\Entity;

use App\Repository\DisponibiliteRepository;
use App\Enum\StatutDisponibiliteEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DisponibiliteRepository::class)]
#[ORM\Table(name: 'disponibilite')]
class Disponibilite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    #[Groups(['disponibilite:read', 'bateau:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'date_debut', type: Types::DATETIME_MUTABLE)]
    #[Groups(['disponibilite:read', 'bateau:read'])]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(name: 'date_fin', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['bateau:read'])]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: 'string', enumType: StatutDisponibiliteEnum::class, length: 20)]
    #[Groups(['bateau:read'])]
    private StatutDisponibiliteEnum $statut = StatutDisponibiliteEnum::DISPONIBLE;

    #[ORM\ManyToOne(targetEntity: Bateau::class, inversedBy: 'disponibilites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bateau $bateau = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateDebut(): \DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getStatut(): StatutDisponibiliteEnum
    {
        return $this->statut;
    }

    public function setStatut(StatutDisponibiliteEnum $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getBateau(): ?Bateau
    {
        return $this->bateau;
    }

    public function setBateau(?Bateau $bateau): static
    {
        $this->bateau = $bateau;

        return $this;
    }
}

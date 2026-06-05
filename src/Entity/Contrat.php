<?php

namespace App\Entity;

use App\Repository\ContratRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContratRepository::class)]
#[ORM\Table(name: 'contrat')]
class Contrat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: Types::TEXT)]
    private string $conditions;

    #[ORM\Column(name: 'assurance_incluse')]
    private bool $assuranceIncluse;

    #[ORM\Column(name: 'statut_contrat', length: 50)]
    private string $statutContrat;

    #[ORM\OneToOne(mappedBy: 'contrat', targetEntity: Reservation::class)]
    private ?Reservation $reservation = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getConditions(): string
    {
        return $this->conditions;
    }

    public function setConditions(string $conditions): static
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function isAssuranceIncluse(): bool
    {
        return $this->assuranceIncluse;
    }

    public function setAssuranceIncluse(bool $assuranceIncluse): static
    {
        $this->assuranceIncluse = $assuranceIncluse;

        return $this;
    }

    public function getStatutContrat(): string
    {
        return $this->statutContrat;
    }

    public function setStatutContrat(string $statutContrat): static
    {
        $this->statutContrat = $statutContrat;

        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }
}

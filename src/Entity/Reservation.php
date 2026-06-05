<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    #[Groups(['reservation:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'date_reservation', type: Types::DATETIME_MUTABLE)]
    #[Groups(['reservation:read'])]
    private \DateTimeInterface $dateReservation;

    #[ORM\Column(name: 'date_debut', type: Types::DATETIME_MUTABLE)]
    #[Groups(['reservation:read'])]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(name: 'date_fin', type: Types::DATETIME_MUTABLE)]
    #[Groups(['reservation:read'])]
    private \DateTimeInterface $dateFin;

    #[ORM\Column(name: 'montant_total', type: Types::DECIMAL, precision: 20, scale: 2)]
    #[Groups(['reservation:read'])]
    private string $montantTotal;

    #[ORM\OneToOne(targetEntity: Contrat::class, inversedBy: 'reservation')]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?Contrat $contrat = null;

    #[ORM\ManyToOne(targetEntity: Bateau::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bateau $bateau = null;

    #[ORM\ManyToOne(targetEntity: StatutReservation::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StatutReservation $statutReservation = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToMany(targetEntity: Assurance::class, inversedBy: 'reservations')]
    #[ORM\JoinTable(name: 'reservation_assurance')]
    private Collection $assurances;

    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'reservation')]
    private Collection $paiements;

    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'reservation')]
    private Collection $avis;

    public function __construct()
    {
        $this->dateReservation = new \DateTime();
        $this->assurances = new ArrayCollection();
        $this->paiements = new ArrayCollection();
        $this->avis = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateReservation(): \DateTimeInterface
    {
        return $this->dateReservation;
    }

    public function setDateReservation(\DateTimeInterface $dateReservation): static
    {
        $this->dateReservation = $dateReservation;

        return $this;
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

    public function getDateFin(): \DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getMontantTotal(): string
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(string $montantTotal): static
    {
        $this->montantTotal = $montantTotal;

        return $this;
    }

    public function getContrat(): ?Contrat
    {
        return $this->contrat;
    }

    public function setContrat(?Contrat $contrat): static
    {
        $this->contrat = $contrat;

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

    public function getStatutReservation(): ?StatutReservation
    {
        return $this->statutReservation;
    }

    public function setStatutReservation(?StatutReservation $statutReservation): static
    {
        $this->statutReservation = $statutReservation;

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

    public function getAssurances(): Collection
    {
        return $this->assurances;
    }

    public function addAssurance(Assurance $assurance): static
    {
        if (!$this->assurances->contains($assurance)) {
            $this->assurances->add($assurance);
        }

        return $this;
    }

    public function removeAssurance(Assurance $assurance): static
    {
        $this->assurances->removeElement($assurance);

        return $this;
    }

    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    public function getAvis(): Collection
    {
        return $this->avis;
    }
}

<?php

namespace App\Entity;

use App\Repository\PaiementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
#[ORM\Table(name: 'paiement')]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    #[Groups(['paiement:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'date_paiement', type: Types::DATETIME_MUTABLE)]
    #[Groups(['paiement:read'])]
    private \DateTimeInterface $datePaiement;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 2)]
    #[Groups(['paiement:read'])]
    private string $montant;

    #[ORM\Column(name: 'statut_paiement', length: 50)]
    #[Groups(['paiement:read'])]
    private string $statutPaiement;

    #[ORM\ManyToOne(targetEntity: StatutPaiement::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?StatutPaiement $statutPaiementRef = null;

    #[ORM\ManyToOne(targetEntity: ModeDePaiement::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ModeDePaiement $modePaiement = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    public function __construct()
    {
        $this->datePaiement = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatePaiement(): \DateTimeInterface
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(\DateTimeInterface $datePaiement): static
    {
        $this->datePaiement = $datePaiement;

        return $this;
    }

    public function getMontant(): string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getStatutPaiement(): string
    {
        return $this->statutPaiement;
    }

    public function setStatutPaiement(string $statutPaiement): static
    {
        $this->statutPaiement = $statutPaiement;

        return $this;
    }

    public function getStatutPaiementRef(): ?StatutPaiement
    {
        return $this->statutPaiementRef;
    }

    public function setStatutPaiementRef(?StatutPaiement $statutPaiementRef): static
    {
        $this->statutPaiementRef = $statutPaiementRef;

        return $this;
    }

    public function getModePaiement(): ?ModeDePaiement
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?ModeDePaiement $modePaiement): static
    {
        $this->modePaiement = $modePaiement;

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

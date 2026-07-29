<?php

namespace App\Entity;

use App\Enum\StatutPaiementEnum;
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

    #[ORM\Column(type: 'string', enumType: StatutPaiementEnum::class, length: 50)]
    #[Groups(['paiement:read'])]
    private StatutPaiementEnum $statutPaiement = StatutPaiementEnum::EN_ATTENTE;

    #[ORM\ManyToOne(targetEntity: ModeDePaiement::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ModeDePaiement $modePaiement = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    #[ORM\Column(name: 'stripe_payment_intent_id', length: 255, nullable: true, unique: true)]
    #[Groups(['paiement:read'])]
    private ?string $stripePaymentIntentId = null;

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

    public function getStatutPaiement(): StatutPaiementEnum
    {
        return $this->statutPaiement;
    }

    public function setStatutPaiement(StatutPaiementEnum $statutPaiement): static
    {
        $this->statutPaiement = $statutPaiement;

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

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): static
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;

        return $this;
    }
}

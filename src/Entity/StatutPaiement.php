<?php

namespace App\Entity;

use App\Repository\StatutPaiementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatutPaiementRepository::class)]
#[ORM\Table(name: 'statut_paiement')]
class StatutPaiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'label_statut_paiement', length: 50, unique: true)]
    private string $labelStatutPaiement;

    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'statutPaiementRef')]
    private Collection $paiements;

    public function __construct()
    {
        $this->paiements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabelStatutPaiement(): string
    {
        return $this->labelStatutPaiement;
    }

    public function setLabelStatutPaiement(string $labelStatutPaiement): static
    {
        $this->labelStatutPaiement = $labelStatutPaiement;

        return $this;
    }

    public function getPaiements(): Collection
    {
        return $this->paiements;
    }
}

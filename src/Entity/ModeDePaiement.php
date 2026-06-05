<?php

namespace App\Entity;

use App\Repository\ModeDePaiementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModeDePaiementRepository::class)]
#[ORM\Table(name: 'mode_de_paiement')]
class ModeDePaiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'label_mode_paiement', length: 150)]
    private string $labelModePaiement;

    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'modePaiement')]
    private Collection $paiements;

    public function __construct()
    {
        $this->paiements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabelModePaiement(): string
    {
        return $this->labelModePaiement;
    }

    public function setLabelModePaiement(string $labelModePaiement): static
    {
        $this->labelModePaiement = $labelModePaiement;

        return $this;
    }

    public function getPaiements(): Collection
    {
        return $this->paiements;
    }
}

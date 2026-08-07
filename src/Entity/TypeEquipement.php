<?php

namespace App\Entity;

use App\Repository\TypeEquipementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TypeEquipementRepository::class)]
#[ORM\Table(name: 'type_equipement')]
#[ORM\UniqueConstraint(name: 'uq_label_type_equipement', columns: ['label_type_equipement'])]
class TypeEquipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['referentiel:read', 'bateau:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'label_type_equipement', length: 100)]
    #[Groups(['referentiel:read', 'bateau:read'])]
    private string $labelTypeEquipement;

    #[ORM\OneToMany(targetEntity: Equipement::class, mappedBy: 'typeEquipement')]
    #[Groups(['referentiel:read'])]
    private Collection $equipements;

    public function __construct()
    {
        $this->equipements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabelTypeEquipement(): string
    {
        return $this->labelTypeEquipement;
    }

    public function setLabelTypeEquipement(string $labelTypeEquipement): static
    {
        $this->labelTypeEquipement = $labelTypeEquipement;

        return $this;
    }

    public function getEquipements(): Collection
    {
        return $this->equipements;
    }

    public function addEquipement(Equipement $equipement): static
    {
        if (!$this->equipements->contains($equipement)) {
            $this->equipements->add($equipement);
            $equipement->setTypeEquipement($this);
        }

        return $this;
    }

    public function removeEquipement(Equipement $equipement): static
    {
        if ($this->equipements->removeElement($equipement)) {
            if ($equipement->getTypeEquipement() === $this) {
                $equipement->setTypeEquipement(null);
            }
        }

        return $this;
    }
}

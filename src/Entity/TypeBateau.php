<?php

namespace App\Entity;

use App\Repository\TypeBateauRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TypeBateauRepository::class)]
#[ORM\Table(name: 'type_bateau')]
class TypeBateau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['referentiel:read', 'bateau:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'label_type_bateau', length: 155)]
    #[Groups(['referentiel:read', 'bateau:read'])]
    private string $labelTypeBateau;

    #[ORM\OneToMany(targetEntity: Bateau::class, mappedBy: 'typeBateau')]
    private Collection $bateaux;

    public function __construct()
    {
        $this->bateaux = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabelTypeBateau(): string
    {
        return $this->labelTypeBateau;
    }

    public function setLabelTypeBateau(string $labelTypeBateau): static
    {
        $this->labelTypeBateau = $labelTypeBateau;

        return $this;
    }

    public function getBateaux(): Collection
    {
        return $this->bateaux;
    }

    public function addBateau(Bateau $bateau): static
    {
        if (!$this->bateaux->contains($bateau)) {
            $this->bateaux->add($bateau);
            $bateau->setTypeBateau($this);
        }

        return $this;
    }

    public function removeBateau(Bateau $bateau): static
    {
        if ($this->bateaux->removeElement($bateau)) {
            if ($bateau->getTypeBateau() === $this) {
                $bateau->setTypeBateau(null);
            }
        }

        return $this;
    }
}

<?php

namespace App\Entity;

use App\Repository\EquipementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: EquipementRepository::class)]
#[ORM\Table(name: 'equipement')]
#[ORM\UniqueConstraint(name: 'uq_nom_equipement', columns: ['nom'])]
class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['referentiel:read', 'bateau:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Groups(['referentiel:read', 'bateau:read'])]
    private string $nom;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['referentiel:read', 'bateau:read'])]
    private ?string $icone = null;

    #[ORM\ManyToOne(targetEntity: TypeEquipement::class, inversedBy: 'equipements')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['referentiel:read', 'bateau:read'])]
    private ?TypeEquipement $typeEquipement = null;

    #[ORM\ManyToMany(targetEntity: Bateau::class, mappedBy: 'equipements')]
    private Collection $bateaux;

    public function __construct()
    {
        $this->bateaux = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getIcone(): ?string
    {
        return $this->icone;
    }

    public function setIcone(?string $icone): static
    {
        $this->icone = $icone;

        return $this;
    }

    public function getTypeEquipement(): ?TypeEquipement
    {
        return $this->typeEquipement;
    }

    public function setTypeEquipement(?TypeEquipement $typeEquipement): static
    {
        $this->typeEquipement = $typeEquipement;

        return $this;
    }

    public function getBateaux(): Collection
    {
        return $this->bateaux;
    }
}

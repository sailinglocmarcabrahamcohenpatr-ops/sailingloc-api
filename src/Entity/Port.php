<?php

namespace App\Entity;

use App\Repository\PortRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PortRepository::class)]
#[ORM\Table(name: 'port')]
class Port
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['port:read', 'bateau:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Groups(['port:read', 'bateau:read'])]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 150, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    private string $nom;

    #[ORM\Column(length: 150)]
    #[Groups(['port:read', 'bateau:read'])]
    #[Assert\NotBlank(message: 'Le pays est obligatoire.')]
    #[Assert\Length(max: 150, maxMessage: 'Le pays ne peut pas dépasser {{ limit }} caractères.')]
    private string $pays;

    #[ORM\Column(length: 150)]
    #[Groups(['port:read', 'bateau:read'])]
    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Length(max: 150, maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères.')]
    private string $ville;

    #[ORM\Column(name: 'code_postal', length: 20, nullable: true)]
    #[Groups(['port:read', 'bateau:read'])]
    private ?string $codePostal = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['port:read', 'bateau:read'])]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $longitude = null;

    #[ORM\OneToMany(targetEntity: Bateau::class, mappedBy: 'port')]
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

    public function getVille(): string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getPays(): string
    {
        return $this->pays;
    }

    public function setPays(string $pays): static
    {
        $this->pays = $pays;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;

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
            $bateau->setPort($this);
        }

        return $this;
    }

    public function removeBateau(Bateau $bateau): static
    {
        if ($this->bateaux->removeElement($bateau)) {
            if ($bateau->getPort() === $this) {
                $bateau->setPort(null);
            }
        }

        return $this;
    }
}

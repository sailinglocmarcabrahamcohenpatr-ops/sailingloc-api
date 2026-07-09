<?php

namespace App\Entity;

use App\Repository\BateauRepository;
use App\Enum\StatutBateauEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: BateauRepository::class)]
#[ORM\Table(name: 'bateau')]
class Bateau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    #[Groups(['bateau:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['bateau:read'])]
    private string $nomBateau;

    #[ORM\Column(length: 255)]
    #[Groups(['bateau:read'])]
    private string $motorisation;

    #[ORM\Column(nullable: true)]
    #[Groups(['bateau:read'])]
    private ?int $capacite = null;

    #[ORM\Column(length: 50)]
    #[Groups(['bateau:read'])]
    private string $taille;

    #[ORM\Column]
    #[Groups(['bateau:read'])]
    private bool $avecSkipper;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['bateau:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', enumType: StatutBateauEnum::class, length: 50)]
    #[Groups(['bateau:read'])]
    private StatutBateauEnum $statut = StatutBateauEnum::INDISPONIBLE;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 2)]
    #[Groups(['bateau:read'])]
    private string $prixJour;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    #[Groups(['bateau:read'])]
    private ?string $prixHeure = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    #[Groups(['bateau:read'])]
    private ?string $caution = null;

    #[ORM\Column]
    #[Groups(['bateau:read'])]
    private bool $carburantInclus = false;

    #[ORM\Column]
    #[Groups(['bateau:read'])]
    private bool $permisRequis = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['bateau:read'])]
    private ?int $nombreCabines = null;

    #[ORM\ManyToOne(targetEntity: Port::class, inversedBy: 'bateaux')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Port $port = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'bateaux')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $proprietaire = null;

    #[ORM\ManyToOne(targetEntity: TypeBateau::class, inversedBy: 'bateaux')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeBateau $typeBateau = null;

    #[ORM\OneToMany(targetEntity: Disponibilite::class, mappedBy: 'bateau')]
    #[Groups(['bateau:read'])]
    private Collection $disponibilites;

    #[ORM\OneToMany(targetEntity: PhotoBateau::class, mappedBy: 'bateau')]
    #[Groups(['bateau:read'])]
    private Collection $photos;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, mappedBy: 'bateauxFavoris')]
    private Collection $utilisateursFavoris;

    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'bateau')]
    private Collection $reservations;

    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'bateau', cascade: ['persist'])]
    #[Groups(['bateau:read'])]
    private Collection $documents;

    public function __construct()
    {
        $this->disponibilites = new ArrayCollection();
        $this->photos = new ArrayCollection();
        $this->utilisateursFavoris = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomBateau(): string
    {
        return $this->nomBateau;
    }

    public function setNomBateau(string $nomBateau): static
    {
        $this->nomBateau = $nomBateau;

        return $this;
    }

    public function getMotorisation(): string
    {
        return $this->motorisation;
    }

    public function setMotorisation(string $motorisation): static
    {
        $this->motorisation = $motorisation;

        return $this;
    }

    public function getCapacite(): ?int
    {
        return $this->capacite;
    }

    public function setCapacite(?int $capacite): static
    {
        $this->capacite = $capacite;

        return $this;
    }

    public function getTaille(): string
    {
        return $this->taille;
    }

    public function setTaille(string $taille): static
    {
        $this->taille = $taille;

        return $this;
    }

    public function isAvecSkipper(): bool
    {
        return $this->avecSkipper;
    }

    public function setAvecSkipper(bool $avecSkipper): static
    {
        $this->avecSkipper = $avecSkipper;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatut(): StatutBateauEnum
    {
        return $this->statut;
    }

    public function setStatut(StatutBateauEnum $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getPrixJour(): string
    {
        return $this->prixJour;
    }

    public function setPrixJour(string $prixJour): static
    {
        $this->prixJour = $prixJour;

        return $this;
    }

    public function getPrixHeure(): ?string
    {
        return $this->prixHeure;
    }

    public function setPrixHeure(?string $prixHeure): static
    {
        $this->prixHeure = $prixHeure;

        return $this;
    }

    public function getCaution(): ?string
    {
        return $this->caution;
    }

    public function setCaution(?string $caution): static
    {
        $this->caution = $caution;

        return $this;
    }

    public function isCarburantInclus(): bool
    {
        return $this->carburantInclus;
    }

    public function setCarburantInclus(bool $carburantInclus): static
    {
        $this->carburantInclus = $carburantInclus;

        return $this;
    }

    public function isPermisRequis(): bool
    {
        return $this->permisRequis;
    }

    public function setPermisRequis(bool $permisRequis): static
    {
        $this->permisRequis = $permisRequis;

        return $this;
    }

    public function getNombreCabines(): ?int
    {
        return $this->nombreCabines;
    }

    public function setNombreCabines(?int $nombreCabines): static
    {
        $this->nombreCabines = $nombreCabines;

        return $this;
    }

    public function getPort(): ?Port
    {
        return $this->port;
    }

    public function setPort(?Port $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getProprietaire(): ?Utilisateur
    {
        return $this->proprietaire;
    }

    public function setProprietaire(?Utilisateur $proprietaire): static
    {
        $this->proprietaire = $proprietaire;

        return $this;
    }

    public function getTypeBateau(): ?TypeBateau
    {
        return $this->typeBateau;
    }

    public function setTypeBateau(?TypeBateau $typeBateau): static
    {
        $this->typeBateau = $typeBateau;

        return $this;
    }

    public function getDisponibilites(): Collection
    {
        return $this->disponibilites;
    }

    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function getUtilisateursFavoris(): Collection
    {
        return $this->utilisateursFavoris;
    }

    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setBateau($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getBateau() === $this) {
                $document->setBateau(null);
            }
        }

        return $this;
    }
}

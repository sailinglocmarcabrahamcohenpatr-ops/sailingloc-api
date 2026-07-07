<?php

namespace App\Entity;

use App\Enum\RoleEnum;
use App\Enum\StatutCompteEnum;
use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    #[Groups(['utilisateur:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['utilisateur:read'])]
    private string $nom;

    #[ORM\Column(length: 255)]
    #[Groups(['utilisateur:read'])]
    private string $prenom;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['utilisateur:read'])]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['utilisateur:read'])]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['utilisateur:read'])]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['utilisateur:read'])]
    private ?string $statutCompte = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $tokenConfirmation = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $tokenResetPassword = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $tokenResetPasswordExpiresAt = null;

    #[ORM\OneToMany(targetEntity: Bateau::class, mappedBy: 'proprietaire')]
    private Collection $bateaux;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['utilisateur:read'])]
    private array $roles = [];

    #[ORM\ManyToMany(targetEntity: Document::class, inversedBy: 'utilisateurs')]
    #[ORM\JoinTable(name: 'document_utilisateur')]
    private Collection $documents;

    #[ORM\ManyToMany(targetEntity: Bateau::class, inversedBy: 'utilisateursFavoris')]
    #[ORM\JoinTable(name: 'favoris')]
    private Collection $bateauxFavoris;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'expediteur')]
    private Collection $messagesEnvoyes;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'destinataire')]
    private Collection $messagesRecus;

    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'utilisateur')]
    private Collection $reservations;

    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'utilisateur')]
    private Collection $avis;

    public function __construct()
    {
        $this->bateaux = new ArrayCollection();
        $this->roles = [RoleEnum::USER->value];
        $this->statutCompte = StatutCompteEnum::INACTIF->value;
        $this->documents = new ArrayCollection();
        $this->bateauxFavoris = new ArrayCollection();
        $this->messagesEnvoyes = new ArrayCollection();
        $this->messagesRecus = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->avis = new ArrayCollection();
        $this->dateInscription = new \DateTime();
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

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getDateInscription(): ?\DateTimeInterface
    {
        return $this->dateInscription;
    }

    public function setDateInscription(?\DateTimeInterface $dateInscription): static
    {
        $this->dateInscription = $dateInscription;

        return $this;
    }

    public function getStatutCompte(): ?string
    {
        return $this->statutCompte;
    }

    public function setStatutCompte(?string $statutCompte): static
    {
        $this->statutCompte = $statutCompte;

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
            $bateau->setProprietaire($this);
        }

        return $this;
    }

    public function removeBateau(Bateau $bateau): static
    {
        if ($this->bateaux->removeElement($bateau)) {
            if ($bateau->getProprietaire() === $this) {
                $bateau->setProprietaire(null);
            }
        }

        return $this;
    }

    public function addRole(RoleEnum $role): static
    {
        if (!in_array($role->value, $this->roles, true)) {
            $this->roles[] = $role->value;
        }

        return $this;
    }

    public function removeRole(RoleEnum $role): static
    {
        $this->roles = array_values(array_filter($this->roles, fn(string $r) => $r !== $role->value));

        return $this;
    }

    public function hasRole(RoleEnum $role): bool
    {
        return in_array($role->value, $this->roles, true);
    }

    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        $this->documents->removeElement($document);

        return $this;
    }

    public function getBateauxFavoris(): Collection
    {
        return $this->bateauxFavoris;
    }

    public function addBateauFavori(Bateau $bateau): static
    {
        if (!$this->bateauxFavoris->contains($bateau)) {
            $this->bateauxFavoris->add($bateau);
        }

        return $this;
    }

    public function removeBateauFavori(Bateau $bateau): static
    {
        $this->bateauxFavoris->removeElement($bateau);

        return $this;
    }

    public function getMessagesEnvoyes(): Collection
    {
        return $this->messagesEnvoyes;
    }

    public function getMessagesRecus(): Collection
    {
        return $this->messagesRecus;
    }

    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function getAvis(): Collection
    {
        return $this->avis;
    }

    // --- UserInterface ---

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_unique(array_merge($this->roles, [RoleEnum::USER->value]));
    }

    public function eraseCredentials(): void {}

    public function getTokenConfirmation(): ?string
    {
        return $this->tokenConfirmation;
    }

    public function setTokenConfirmation(?string $tokenConfirmation): static
    {
        $this->tokenConfirmation = $tokenConfirmation;

        return $this;
    }

    public function getTokenResetPassword(): ?string
    {
        return $this->tokenResetPassword;
    }

    public function setTokenResetPassword(?string $tokenResetPassword): static
    {
        $this->tokenResetPassword = $tokenResetPassword;

        return $this;
    }

    public function getTokenResetPasswordExpiresAt(): ?\DateTimeInterface
    {
        return $this->tokenResetPasswordExpiresAt;
    }

    public function setTokenResetPasswordExpiresAt(?\DateTimeInterface $expiresAt): static
    {
        $this->tokenResetPasswordExpiresAt = $expiresAt;

        return $this;
    }
}

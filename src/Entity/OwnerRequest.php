<?php

namespace App\Entity;

use App\Enum\OwnerRequestStatusEnum;
use App\Enum\OwnerTypeEnum;
use App\Repository\OwnerRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: OwnerRequestRepository::class)]
#[ORM\Table(name: 'owner_request')]
class OwnerRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    #[Groups(['owner_request:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['owner_request:read'])]
    private ?Utilisateur $user = null;

    #[ORM\Column(type: 'string', enumType: OwnerTypeEnum::class, length: 20)]
    #[Groups(['owner_request:read'])]
    private OwnerTypeEnum $ownerType = OwnerTypeEnum::PARTICULIER;

    #[ORM\Column(length: 30)]
    #[Groups(['owner_request:read'])]
    private string $phone;

    #[ORM\Column(length: 255)]
    #[Groups(['owner_request:read'])]
    private string $address;

    #[ORM\Column(length: 100)]
    #[Groups(['owner_request:read'])]
    private string $city;

    #[ORM\Column(length: 20)]
    #[Groups(['owner_request:read'])]
    private string $postalCode;

    #[ORM\Column(length: 100)]
    #[Groups(['owner_request:read'])]
    private string $country = 'France';

    // Professionnel uniquement
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['owner_request:read'])]
    private ?string $companyName = null;

    #[ORM\Column(length: 14, nullable: true)]
    #[Groups(['owner_request:read'])]
    private ?string $siret = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['owner_request:read'])]
    private ?string $vatNumber = null;

    // Justificatifs (FK vers documents existants)
    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['owner_request:read'])]
    private ?Document $identityDocument = null;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['owner_request:read'])]
    private ?Document $proofAddressDocument = null;

    #[ORM\Column(type: 'string', enumType: OwnerRequestStatusEnum::class, length: 20)]
    #[Groups(['owner_request:read'])]
    private OwnerRequestStatusEnum $status = OwnerRequestStatusEnum::PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['owner_request:read'])]
    private ?string $adminComment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['owner_request:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['owner_request:read'])]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['owner_request:read'])]
    private ?Utilisateur $validatedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?Utilisateur { return $this->user; }
    public function setUser(?Utilisateur $user): static { $this->user = $user; return $this; }

    public function getOwnerType(): OwnerTypeEnum { return $this->ownerType; }
    public function setOwnerType(OwnerTypeEnum $ownerType): static { $this->ownerType = $ownerType; return $this; }

    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $phone): static { $this->phone = $phone; return $this; }

    public function getAddress(): string { return $this->address; }
    public function setAddress(string $address): static { $this->address = $address; return $this; }

    public function getCity(): string { return $this->city; }
    public function setCity(string $city): static { $this->city = $city; return $this; }

    public function getPostalCode(): string { return $this->postalCode; }
    public function setPostalCode(string $postalCode): static { $this->postalCode = $postalCode; return $this; }

    public function getCountry(): string { return $this->country; }
    public function setCountry(string $country): static { $this->country = $country; return $this; }

    public function getCompanyName(): ?string { return $this->companyName; }
    public function setCompanyName(?string $companyName): static { $this->companyName = $companyName; return $this; }

    public function getSiret(): ?string { return $this->siret; }
    public function setSiret(?string $siret): static { $this->siret = $siret; return $this; }

    public function getVatNumber(): ?string { return $this->vatNumber; }
    public function setVatNumber(?string $vatNumber): static { $this->vatNumber = $vatNumber; return $this; }

    public function getIdentityDocument(): ?Document { return $this->identityDocument; }
    public function setIdentityDocument(?Document $identityDocument): static { $this->identityDocument = $identityDocument; return $this; }

    public function getProofAddressDocument(): ?Document { return $this->proofAddressDocument; }
    public function setProofAddressDocument(?Document $proofAddressDocument): static { $this->proofAddressDocument = $proofAddressDocument; return $this; }

    public function getStatus(): OwnerRequestStatusEnum { return $this->status; }
    public function setStatus(OwnerRequestStatusEnum $status): static { $this->status = $status; return $this; }

    public function getAdminComment(): ?string { return $this->adminComment; }
    public function setAdminComment(?string $adminComment): static { $this->adminComment = $adminComment; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function getValidatedAt(): ?\DateTimeInterface { return $this->validatedAt; }
    public function setValidatedAt(?\DateTimeInterface $validatedAt): static { $this->validatedAt = $validatedAt; return $this; }

    public function getValidatedBy(): ?Utilisateur { return $this->validatedBy; }
    public function setValidatedBy(?Utilisateur $validatedBy): static { $this->validatedBy = $validatedBy; return $this; }
}

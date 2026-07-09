<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'document')]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    #[Groups(['document:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'url_document', type: Types::TEXT)]
    #[Groups(['document:read'])]
    private string $urlDocument;

    #[ORM\Column(name: 'date_upload', type: Types::DATETIME_MUTABLE)]
    #[Groups(['document:read'])]
    private \DateTimeInterface $dateUpload;

    #[ORM\ManyToOne(targetEntity: TypeDocument::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['document:read'])]
    private ?TypeDocument $typeDocument = null;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class, mappedBy: 'documents')]
    private Collection $utilisateurs;

    #[ORM\ManyToOne(targetEntity: Bateau::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['document:read'])]
    private ?Bateau $bateau = null;

    public function __construct()
    {
        $this->dateUpload = new \DateTime();
        $this->utilisateurs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrlDocument(): string
    {
        return $this->urlDocument;
    }

    public function setUrlDocument(string $urlDocument): static
    {
        $this->urlDocument = $urlDocument;

        return $this;
    }

    public function getDateUpload(): \DateTimeInterface
    {
        return $this->dateUpload;
    }

    public function setDateUpload(\DateTimeInterface $dateUpload): static
    {
        $this->dateUpload = $dateUpload;

        return $this;
    }

    public function getTypeDocument(): ?TypeDocument
    {
        return $this->typeDocument;
    }

    public function setTypeDocument(?TypeDocument $typeDocument): static
    {
        $this->typeDocument = $typeDocument;

        return $this;
    }

    public function getUtilisateurs(): Collection
    {
        return $this->utilisateurs;
    }

    public function getBateau(): ?Bateau
    {
        return $this->bateau;
    }

    public function setBateau(?Bateau $bateau): static
    {
        $this->bateau = $bateau;

        return $this;
    }
}

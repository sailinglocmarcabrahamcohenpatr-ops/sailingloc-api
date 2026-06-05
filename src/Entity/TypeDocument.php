<?php

namespace App\Entity;

use App\Repository\TypeDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeDocumentRepository::class)]
#[ORM\Table(name: 'type_document')]
class TypeDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'label_type_document', length: 150)]
    private string $labelTypeDocument;

    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'typeDocument')]
    private Collection $documents;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabelTypeDocument(): string
    {
        return $this->labelTypeDocument;
    }

    public function setLabelTypeDocument(string $labelTypeDocument): static
    {
        $this->labelTypeDocument = $labelTypeDocument;

        return $this;
    }

    public function getDocuments(): Collection
    {
        return $this->documents;
    }
}

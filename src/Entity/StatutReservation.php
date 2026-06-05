<?php

namespace App\Entity;

use App\Repository\StatutReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatutReservationRepository::class)]
#[ORM\Table(name: 'statut_reservation')]
class StatutReservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'label_statut_reservation', length: 150)]
    private string $labelStatutReservation;

    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'statutReservation')]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabelStatutReservation(): string
    {
        return $this->labelStatutReservation;
    }

    public function setLabelStatutReservation(string $labelStatutReservation): static
    {
        $this->labelStatutReservation = $labelStatutReservation;

        return $this;
    }

    public function getReservations(): Collection
    {
        return $this->reservations;
    }
}

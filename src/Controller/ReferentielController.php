<?php

namespace App\Controller;

use App\Repository\AssuranceRepository;
use App\Repository\ModeDePaiementRepository;
use App\Repository\RoleRepository;
use App\Repository\StatutPaiementRepository;
use App\Repository\StatutReservationRepository;
use App\Repository\TypeBateauRepository;
use App\Repository\TypeDocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/referentiels', name: 'api_referentiels_')]
class ReferentielController extends AbstractController
{
    public function __construct(
        private readonly TypeBateauRepository $typeBateauRepository,
        private readonly TypeDocumentRepository $typeDocumentRepository,
        private readonly RoleRepository $roleRepository,
        private readonly StatutReservationRepository $statutReservationRepository,
        private readonly ModeDePaiementRepository $modePaiementRepository,
        private readonly StatutPaiementRepository $statutPaiementRepository,
        private readonly AssuranceRepository $assuranceRepository,
    ) {}

    #[Route('/types-bateaux', name: 'types_bateaux', methods: ['GET'])]
    public function typesBateaux(): JsonResponse
    {
        return $this->json($this->typeBateauRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[Route('/types-documents', name: 'types_documents', methods: ['GET'])]
    public function typesDocuments(): JsonResponse
    {
        return $this->json($this->typeDocumentRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[Route('/roles', name: 'roles', methods: ['GET'])]
    public function roles(): JsonResponse
    {
        return $this->json($this->roleRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[Route('/statuts-reservations', name: 'statuts_reservations', methods: ['GET'])]
    public function statutsReservations(): JsonResponse
    {
        return $this->json($this->statutReservationRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[Route('/modes-paiements', name: 'modes_paiements', methods: ['GET'])]
    public function modesPaiements(): JsonResponse
    {
        return $this->json($this->modePaiementRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[Route('/statuts-paiements', name: 'statuts_paiements', methods: ['GET'])]
    public function statutsPaiements(): JsonResponse
    {
        return $this->json($this->statutPaiementRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[Route('/assurances', name: 'assurances', methods: ['GET'])]
    public function assurances(): JsonResponse
    {
        return $this->json($this->assuranceRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }
}

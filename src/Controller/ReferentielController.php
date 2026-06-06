<?php

namespace App\Controller;

use App\Enum\RoleEnum;
use App\Repository\AssuranceRepository;
use App\Repository\ModeDePaiementRepository;
use App\Repository\StatutPaiementRepository;
use App\Repository\StatutReservationRepository;
use App\Repository\TypeBateauRepository;
use App\Repository\TypeDocumentRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Référentiels')]
#[Route('/api/referentiels', name: 'api_referentiels_')]
class ReferentielController extends AbstractController
{
    public function __construct(
        private readonly TypeBateauRepository $typeBateauRepository,
        private readonly TypeDocumentRepository $typeDocumentRepository,
        private readonly StatutReservationRepository $statutReservationRepository,
        private readonly ModeDePaiementRepository $modePaiementRepository,
        private readonly StatutPaiementRepository $statutPaiementRepository,
        private readonly AssuranceRepository $assuranceRepository,
    ) {}

    #[OA\Get(path: '/api/referentiels/types-bateaux', summary: 'Types de bateaux', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/types-bateaux', name: 'types_bateaux', methods: ['GET'])]
    public function typesBateaux(): JsonResponse
    {
        return $this->json($this->typeBateauRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Get(path: '/api/referentiels/types-documents', summary: 'Types de documents', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/types-documents', name: 'types_documents', methods: ['GET'])]
    public function typesDocuments(): JsonResponse
    {
        return $this->json($this->typeDocumentRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Get(path: '/api/referentiels/roles', summary: 'Rôles disponibles (ROLE_USER, ROLE_PROPRIETAIRE, ROLE_ADMIN)', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/roles', name: 'roles', methods: ['GET'])]
    public function roles(): JsonResponse
    {
        $roles = array_map(fn(RoleEnum $r) => ['value' => $r->value, 'name' => $r->name], RoleEnum::cases());

        return $this->json($roles, Response::HTTP_OK);
    }

    #[OA\Get(path: '/api/referentiels/statuts-reservations', summary: 'Statuts de réservation', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/statuts-reservations', name: 'statuts_reservations', methods: ['GET'])]
    public function statutsReservations(): JsonResponse
    {
        return $this->json($this->statutReservationRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Get(path: '/api/referentiels/modes-paiements', summary: 'Modes de paiement', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/modes-paiements', name: 'modes_paiements', methods: ['GET'])]
    public function modesPaiements(): JsonResponse
    {
        return $this->json($this->modePaiementRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Get(path: '/api/referentiels/statuts-paiements', summary: 'Statuts de paiement', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/statuts-paiements', name: 'statuts_paiements', methods: ['GET'])]
    public function statutsPaiements(): JsonResponse
    {
        return $this->json($this->statutPaiementRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Get(path: '/api/referentiels/assurances', summary: 'Types d\'assurances', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/assurances', name: 'assurances', methods: ['GET'])]
    public function assurances(): JsonResponse
    {
        return $this->json($this->assuranceRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }
}

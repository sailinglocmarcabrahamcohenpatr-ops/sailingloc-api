<?php

namespace App\Controller;

use App\Entity\OwnerRequest;
use App\Entity\Utilisateur;
use App\Enum\OwnerRequestStatusEnum;
use App\Enum\OwnerTypeEnum;
use App\Enum\RoleEnum;
use App\Repository\DocumentRepository;
use App\Repository\OwnerRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Demandes propriétaire')]
#[Route('/api/owner-requests', name: 'api_owner_requests_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class OwnerRequestController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OwnerRequestRepository $repository,
        private readonly DocumentRepository $documentRepository,
    ) {}

    #[OA\Get(
        path: '/api/owner-requests',
        summary: 'Lister les demandes (ADMIN : toutes, USER : les siennes)',
        responses: [new OA\Response(response: 200, description: 'Liste des demandes')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $requests = $this->repository->findBy([], ['createdAt' => 'DESC']);
        } else {
            /** @var Utilisateur $user */
            $user = $this->getUser();
            $requests = $this->repository->findByUser($user);
        }

        return $this->json($requests, Response::HTTP_OK, [], ['groups' => ['owner_request:read']]);
    }

    #[OA\Get(
        path: '/api/owner-requests/{id}',
        summary: 'Détail d\'une demande',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Demande trouvée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $ownerRequest = $this->repository->find($id);

        if (!$ownerRequest) {
            return $this->json(['message' => 'Demande non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $ownerRequest->getUser() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($ownerRequest, Response::HTTP_OK, [], ['groups' => ['owner_request:read']]);
    }

    #[OA\Post(
        path: '/api/owner-requests',
        summary: 'Soumettre une demande de statut propriétaire',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['owner_type', 'phone', 'address', 'city', 'postal_code'],
                properties: [
                    new OA\Property(property: 'owner_type', type: 'string', enum: ['particulier', 'professionnel'], example: 'particulier'),
                    new OA\Property(property: 'phone', type: 'string', example: '+33612345678'),
                    new OA\Property(property: 'address', type: 'string', example: '12 rue de la Mer'),
                    new OA\Property(property: 'city', type: 'string', example: 'Marseille'),
                    new OA\Property(property: 'postal_code', type: 'string', example: '13001'),
                    new OA\Property(property: 'country', type: 'string', example: 'France', nullable: true),
                    new OA\Property(property: 'company_name', type: 'string', nullable: true, description: 'Requis si professionnel'),
                    new OA\Property(property: 'siret', type: 'string', nullable: true, description: 'Requis si professionnel'),
                    new OA\Property(property: 'vat_number', type: 'string', nullable: true),
                    new OA\Property(property: 'identity_document_id', type: 'integer', nullable: true, description: 'ID du document pièce d\'identité (uploadé via POST /api/documents)'),
                    new OA\Property(property: 'proof_address_document_id', type: 'integer', nullable: true, description: 'ID du document justificatif de domicile'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Demande soumise'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 409, description: 'Une demande est déjà en attente'),
        ]
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if ($this->repository->hasPendingRequest($user)) {
            return $this->json(['message' => 'Une demande est déjà en cours de traitement.'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['owner_type', 'phone', 'address', 'city', 'postal_code'];
        $missing = array_filter($required, fn($f) => empty($data[$f]));
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $ownerType = OwnerTypeEnum::tryFrom($data['owner_type']);
        if ($ownerType === null) {
            return $this->json(['message' => 'owner_type invalide. Valeurs : particulier, professionnel.'], Response::HTTP_BAD_REQUEST);
        }

        if ($ownerType === OwnerTypeEnum::PROFESSIONNEL && empty($data['company_name'])) {
            return $this->json(['message' => 'Le champ company_name est requis pour un compte professionnel.'], Response::HTTP_BAD_REQUEST);
        }

        $ownerRequest = new OwnerRequest();
        $ownerRequest->setUser($user);
        $ownerRequest->setOwnerType($ownerType);
        $ownerRequest->setPhone($data['phone']);
        $ownerRequest->setAddress($data['address']);
        $ownerRequest->setCity($data['city']);
        $ownerRequest->setPostalCode($data['postal_code']);
        $ownerRequest->setCountry($data['country'] ?? 'France');
        $ownerRequest->setCompanyName($data['company_name'] ?? null);
        $ownerRequest->setSiret($data['siret'] ?? null);
        $ownerRequest->setVatNumber($data['vat_number'] ?? null);

        if (!empty($data['identity_document_id'])) {
            $doc = $this->documentRepository->find((int) $data['identity_document_id']);
            if (!$doc) {
                return $this->json(['message' => 'Document pièce d\'identité introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $ownerRequest->setIdentityDocument($doc);
        }

        if (!empty($data['proof_address_document_id'])) {
            $doc = $this->documentRepository->find((int) $data['proof_address_document_id']);
            if (!$doc) {
                return $this->json(['message' => 'Document justificatif de domicile introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $ownerRequest->setProofAddressDocument($doc);
        }

        $this->em->persist($ownerRequest);
        $this->em->flush();

        return $this->json($ownerRequest, Response::HTTP_CREATED, [], ['groups' => ['owner_request:read']]);
    }

    #[OA\Put(
        path: '/api/owner-requests/{id}',
        summary: 'Approuver ou rejeter une demande (ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['approved', 'rejected'], example: 'approved'),
                    new OA\Property(property: 'admin_comment', type: 'string', nullable: true, example: 'Documents insuffisants.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Demande mise à jour'),
            new OA\Response(response: 400, description: 'Statut invalide'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $ownerRequest = $this->repository->find($id);

        if (!$ownerRequest) {
            return $this->json(['message' => 'Demande non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        $newStatus = OwnerRequestStatusEnum::tryFrom($data['status'] ?? '');
        if ($newStatus === null || $newStatus === OwnerRequestStatusEnum::PENDING) {
            return $this->json(['message' => 'Statut invalide. Valeurs acceptées : approved, rejected.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var Utilisateur $admin */
        $admin = $this->getUser();

        $ownerRequest->setStatus($newStatus);
        $ownerRequest->setAdminComment($data['admin_comment'] ?? null);
        $ownerRequest->setValidatedAt(new \DateTime());
        $ownerRequest->setValidatedBy($admin);

        // Si approuvé → attribuer le rôle PROPRIETAIRE
        if ($newStatus === OwnerRequestStatusEnum::APPROVED) {
            $ownerRequest->getUser()->addRole(RoleEnum::PROPRIETAIRE);
        }

        $this->em->flush();

        return $this->json($ownerRequest, Response::HTTP_OK, [], ['groups' => ['owner_request:read']]);
    }
}

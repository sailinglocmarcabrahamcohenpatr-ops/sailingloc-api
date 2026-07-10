<?php

namespace App\Controller;

use App\Entity\Paiement;
use App\Repository\ModeDePaiementRepository;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use App\Repository\StatutPaiementRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Paiements')]
#[Route('/api/paiements', name: 'api_paiements_')]
class PaiementController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaiementRepository $repository,
        private readonly ReservationRepository $reservationRepository,
        private readonly StatutPaiementRepository $statutPaiementRepository,
        private readonly ModeDePaiementRepository $modePaiementRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[OA\Get(
        path: '/api/paiements',
        summary: 'Lister tous les paiements',
        responses: [new OA\Response(response: 200, description: 'Liste des paiements')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->repository->findAll(), Response::HTTP_OK, [], ['groups' => ['paiement:read']]);
    }

    #[OA\Get(
        path: '/api/paiements/{id}',
        summary: 'Détail d\'un paiement',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Paiement trouvé'),
            new OA\Response(response: 404, description: 'Paiement non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $paiement = $this->repository->find($id);

        if (!$paiement) {
            return $this->json(['message' => 'Paiement non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($paiement, Response::HTTP_OK, [], ['groups' => ['paiement:read']]);
    }

    #[OA\Post(
        path: '/api/paiements',
        summary: 'Enregistrer un paiement',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['montant', 'id_reservation', 'id_statut_paiement', 'id_mode_paiement'],
                properties: [
                    new OA\Property(property: 'montant', type: 'number', example: 875),
                    new OA\Property(property: 'id_reservation', type: 'integer', example: 1),
                    new OA\Property(property: 'id_statut_paiement', type: 'integer', example: 1),
                    new OA\Property(property: 'id_mode_paiement', type: 'integer', example: 1),
                    new OA\Property(property: 'date_paiement', type: 'string', format: 'date', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Paiement créé'),
            new OA\Response(response: 400, description: 'Données invalides'),
        ]
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['montant', 'id_reservation', 'id_statut_paiement', 'id_mode_paiement'];
        $missing = array_filter($required, fn($f) => !isset($data[$f]) || $data[$f] === '' || $data[$f] === null);
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $reservation = $this->reservationRepository->find($data['id_reservation']);
        $statutRef = $this->statutPaiementRepository->find($data['id_statut_paiement']);
        $mode = $this->modePaiementRepository->find($data['id_mode_paiement']);

        if (!$reservation || !$statutRef || !$mode) {
            return $this->json(['message' => 'Réservation, statut ou mode de paiement introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $paiement = new Paiement();
        $paiement->setMontant((string) ($data['montant'] ?? '0'));
        $paiement->setReservation($reservation);
        $paiement->setStatutPaiementRef($statutRef);
        $paiement->setModePaiement($mode);

        if (isset($data['date_paiement'])) {
            $paiement->setDatePaiement(new \DateTime($data['date_paiement']));
        }

        $errors = $this->validator->validate($paiement);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($paiement);
        $this->em->flush();

        return $this->json($paiement, Response::HTTP_CREATED, [], ['groups' => ['paiement:read']]);
    }

    #[OA\Put(
        path: '/api/paiements/{id}',
        summary: 'Modifier un paiement',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Mise à jour réussie'),
            new OA\Response(response: 404, description: 'Paiement non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $paiement = $this->repository->find($id);

        if (!$paiement) {
            return $this->json(['message' => 'Paiement non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['montant'])) $paiement->setMontant((string) $data['montant']);
        if (isset($data['date_paiement'])) $paiement->setDatePaiement(new \DateTime($data['date_paiement']));

        if (isset($data['id_statut_paiement'])) {
            $statut = $this->statutPaiementRepository->find($data['id_statut_paiement']);
            if (!$statut) return $this->json(['message' => 'Statut introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $paiement->setStatutPaiementRef($statut);
        }

        $errors = $this->validator->validate($paiement);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json($paiement, Response::HTTP_OK, [], ['groups' => ['paiement:read']]);
    }

    #[OA\Delete(
        path: '/api/paiements/{id}',
        summary: 'Supprimer un paiement',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 404, description: 'Paiement non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $paiement = $this->repository->find($id);

        if (!$paiement) {
            return $this->json(['message' => 'Paiement non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($paiement);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

<?php

namespace App\Controller;

use App\Entity\Disponibilite;
use App\Enum\StatutDisponibiliteEnum;
use App\Repository\BateauRepository;
use App\Repository\DisponibiliteRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Disponibilités')]
#[Route('/api/disponibilites', name: 'api_disponibilites_')]
class DisponibiliteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DisponibiliteRepository $repository,
        private readonly BateauRepository $bateauRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[OA\Get(
        path: '/api/disponibilites',
        summary: 'Lister toutes les disponibilités',
        parameters: [
            new OA\Parameter(name: 'page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste paginée des disponibilités')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $dispos = $this->repository->findBy([], null, $limit, $offset);
        $total  = $this->repository->count([]);

        return $this->json([
            'data'       => $dispos,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / max(1, $limit)),
            ],
        ], Response::HTTP_OK, [], ['groups' => ['disponibilite:read']]);
    }

    #[OA\Get(
        path: '/api/disponibilites/{id}',
        summary: 'Détail d\'une disponibilité',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Disponibilité trouvée'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $dispo = $this->repository->find($id);

        if (!$dispo) {
            return $this->json(['message' => 'Disponibilité non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($dispo, Response::HTTP_OK, [], ['groups' => ['disponibilite:read']]);
    }

    #[OA\Post(
        path: '/api/disponibilites',
        summary: 'Créer une disponibilité (PROPRIETAIRE du bateau)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['date_debut', 'id_bateau'],
                properties: [
                    new OA\Property(property: 'date_debut', type: 'string', format: 'date', example: '2026-07-01'),
                    new OA\Property(property: 'date_fin', type: 'string', format: 'date', example: '2026-07-31', nullable: true),
                    new OA\Property(property: 'statut', type: 'string', example: 'disponible'),
                    new OA\Property(property: 'id_bateau', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Créée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_PROPRIETAIRE')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['date_debut', 'id_bateau'];
        $missing = array_filter($required, fn($f) => empty($data[$f]));
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $bateau = $this->bateauRepository->find($data['id_bateau']);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $bateau->getProprietaire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $statut = StatutDisponibiliteEnum::tryFrom($data['statut'] ?? StatutDisponibiliteEnum::DISPONIBLE->value);
        if ($statut === null) {
            return $this->json(['message' => 'Statut invalide.', 'valeurs' => array_column(StatutDisponibiliteEnum::cases(), 'value')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $dispo = new Disponibilite();
        $dispo->setDateDebut(new \DateTime($data['date_debut']));
        $dispo->setDateFin(isset($data['date_fin']) ? new \DateTime($data['date_fin']) : null);
        $dispo->setStatut($statut);
        $dispo->setBateau($bateau);

        $errors = $this->validator->validate($dispo);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($dispo);
        $this->em->flush();

        return $this->json($dispo, Response::HTTP_CREATED, [], ['groups' => ['disponibilite:read']]);
    }

    #[OA\Put(
        path: '/api/disponibilites/{id}',
        summary: 'Modifier une disponibilité (propriétaire du bateau ou ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Mise à jour réussie'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $dispo = $this->repository->find($id);

        if (!$dispo) {
            return $this->json(['message' => 'Disponibilité non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $dispo->getBateau()->getProprietaire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['date_debut'])) $dispo->setDateDebut(new \DateTime($data['date_debut']));
        if (array_key_exists('date_fin', $data)) $dispo->setDateFin($data['date_fin'] ? new \DateTime($data['date_fin']) : null);
        if (isset($data['statut'])) $dispo->setStatut($data['statut']);

        $this->em->flush();

        return $this->json($dispo, Response::HTTP_OK, [], ['groups' => ['disponibilite:read']]);
    }

    #[OA\Delete(
        path: '/api/disponibilites/{id}',
        summary: 'Supprimer une disponibilité (propriétaire du bateau ou ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $dispo = $this->repository->find($id);

        if (!$dispo) {
            return $this->json(['message' => 'Disponibilité non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $dispo->getBateau()->getProprietaire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($dispo);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

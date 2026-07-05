<?php

namespace App\Controller;

use App\Entity\Port;
use App\Repository\PortRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Ports')]
#[Route('/api/ports', name: 'api_ports_')]
class PortController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PortRepository $repository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[OA\Get(
        path: '/api/ports',
        summary: 'Lister tous les ports',
        parameters: [
            new OA\Parameter(name: 'page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste paginée des ports')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $ports = $this->repository->findBy([], ['nom' => 'ASC'], $limit, $offset);
        $total = $this->repository->count([]);

        return $this->json([
            'data'       => $ports,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / max(1, $limit)),
            ],
        ], Response::HTTP_OK, [], ['groups' => ['port:read']]);
    }

    #[OA\Get(
        path: '/api/ports/{id}',
        summary: 'Détail d\'un port',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Port trouvé'),
            new OA\Response(response: 404, description: 'Port non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $port = $this->repository->find($id);

        if (!$port) {
            return $this->json(['message' => 'Port non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($port, Response::HTTP_OK, [], ['groups' => ['port:read']]);
    }

    #[OA\Post(
        path: '/api/ports',
        summary: 'Créer un port (PROPRIETAIRE)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'pays', 'ville'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Port de Marseille'),
                    new OA\Property(property: 'pays', type: 'string', example: 'France'),
                    new OA\Property(property: 'ville', type: 'string', example: 'Marseille'),
                    new OA\Property(property: 'code_postal', type: 'string', example: '13001', nullable: true),
                    new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 43.2965, nullable: true),
                    new OA\Property(property: 'longitude', type: 'number', format: 'float', example: 5.3698, nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Port créé'),
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

        $required = ['nom', 'pays', 'ville'];
        $missing = array_filter($required, fn($f) => empty($data[$f]));
        if ($missing) {
            return $this->json([
                'message' => 'Champs obligatoires manquants.',
                'champs'  => array_values($missing),
            ], Response::HTTP_BAD_REQUEST);
        }

        $port = new Port();
        $port->setNom($data['nom'] ?? '');
        $port->setPays($data['pays'] ?? '');
        $port->setVille($data['ville'] ?? '');
        $port->setCodePostal($data['code_postal'] ?? null);
        $port->setLatitude($data['latitude'] ?? null);
        $port->setLongitude($data['longitude'] ?? null);

        $errors = $this->validator->validate($port);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($port);
        $this->em->flush();

        return $this->json($port, Response::HTTP_CREATED, [], ['groups' => ['port:read']]);
    }

    #[OA\Put(
        path: '/api/ports/{id}',
        summary: 'Modifier un port (ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Port mis à jour'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Port non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $port = $this->repository->find($id);

        if (!$port) {
            return $this->json(['message' => 'Port non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['nom'])) $port->setNom($data['nom']);
        if (isset($data['pays'])) $port->setPays($data['pays']);
        if (isset($data['ville'])) $port->setVille($data['ville']);
        if (array_key_exists('code_postal', $data)) $port->setCodePostal($data['code_postal']);
        if (array_key_exists('latitude', $data)) $port->setLatitude($data['latitude']);
        if (array_key_exists('longitude', $data)) $port->setLongitude($data['longitude']);

        $errors = $this->validator->validate($port);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json($port, Response::HTTP_OK, [], ['groups' => ['port:read']]);
    }

    #[OA\Delete(
        path: '/api/ports/{id}',
        summary: 'Supprimer un port (ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Port non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $port = $this->repository->find($id);

        if (!$port) {
            return $this->json(['message' => 'Port non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($port);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

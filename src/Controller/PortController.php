<?php

namespace App\Controller;

use App\Entity\Port;
use App\Repository\PortRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/ports', name: 'api_ports_')]
class PortController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PortRepository $repository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->repository->findAll(), Response::HTTP_OK, [], ['groups' => ['port:read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $port = $this->repository->find($id);

        if (!$port) {
            return $this->json(['message' => 'Port non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($port, Response::HTTP_OK, [], ['groups' => ['port:read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
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

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
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

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
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

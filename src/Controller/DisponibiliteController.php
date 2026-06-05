<?php

namespace App\Controller;

use App\Entity\Disponibilite;
use App\Repository\BateauRepository;
use App\Repository\DisponibiliteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/disponibilites', name: 'api_disponibilites_')]
class DisponibiliteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DisponibiliteRepository $repository,
        private readonly BateauRepository $bateauRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->repository->findAll(), Response::HTTP_OK, [], ['groups' => ['disponibilite:read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $dispo = $this->repository->find($id);

        if (!$dispo) {
            return $this->json(['message' => 'Disponibilité non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($dispo, Response::HTTP_OK, [], ['groups' => ['disponibilite:read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
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

        $dispo = new Disponibilite();
        $dispo->setDateDebut(new \DateTime($data['date_debut']));
        $dispo->setDateFin(isset($data['date_fin']) ? new \DateTime($data['date_fin']) : null);
        $dispo->setStatut($data['statut'] ?? 'disponible');
        $dispo->setBateau($bateau);

        $errors = $this->validator->validate($dispo);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($dispo);
        $this->em->flush();

        return $this->json($dispo, Response::HTTP_CREATED, [], ['groups' => ['disponibilite:read']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $dispo = $this->repository->find($id);

        if (!$dispo) {
            return $this->json(['message' => 'Disponibilité non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['date_debut'])) $dispo->setDateDebut(new \DateTime($data['date_debut']));
        if (array_key_exists('date_fin', $data)) $dispo->setDateFin($data['date_fin'] ? new \DateTime($data['date_fin']) : null);
        if (isset($data['statut'])) $dispo->setStatut($data['statut']);

        $this->em->flush();

        return $this->json($dispo, Response::HTTP_OK, [], ['groups' => ['disponibilite:read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $dispo = $this->repository->find($id);

        if (!$dispo) {
            return $this->json(['message' => 'Disponibilité non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($dispo);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

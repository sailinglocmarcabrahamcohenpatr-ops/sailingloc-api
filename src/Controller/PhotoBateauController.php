<?php

namespace App\Controller;

use App\Entity\PhotoBateau;
use App\Repository\BateauRepository;
use App\Repository\PhotoBateauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/photos', name: 'api_photos_')]
class PhotoBateauController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PhotoBateauRepository $repository,
        private readonly BateauRepository $bateauRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->repository->findAll(), Response::HTTP_OK, [], ['groups' => ['photo:read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $photo = $this->repository->find($id);

        if (!$photo) {
            return $this->json(['message' => 'Photo non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($photo, Response::HTTP_OK, [], ['groups' => ['photo:read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['url', 'id_bateau'];
        $missing = array_filter($required, fn($f) => empty($data[$f]));
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $bateau = $this->bateauRepository->find($data['id_bateau']);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $photo = new PhotoBateau();
        $photo->setUrl($data['url'] ?? '');
        $photo->setDescription($data['description'] ?? null);
        $photo->setOrdreAffichage($data['ordre_affichage'] ?? null);
        $photo->setBateau($bateau);

        $errors = $this->validator->validate($photo);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($photo);
        $this->em->flush();

        return $this->json($photo, Response::HTTP_CREATED, [], ['groups' => ['photo:read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $photo = $this->repository->find($id);

        if (!$photo) {
            return $this->json(['message' => 'Photo non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($photo);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

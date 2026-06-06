<?php

namespace App\Controller;

use App\Entity\PhotoBateau;
use App\Repository\BateauRepository;
use App\Repository\PhotoBateauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/photos', name: 'api_photos_')]
class PhotoBateauController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PhotoBateauRepository $repository,
        private readonly BateauRepository $bateauRepository,
        private readonly ValidatorInterface $validator,
        private readonly SluggerInterface $slugger,
        #[Autowire('%upload_directory%')] private readonly string $uploadDirectory,
        #[Autowire('%upload_base_url%')] private readonly string $uploadBaseUrl,
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
    #[IsGranted('ROLE_PROPRIETAIRE')]
    public function create(Request $request): JsonResponse
    {
        $bateauId = $request->request->get('id_bateau');
        $file = $request->files->get('photo');

        if (!$bateauId || !$file) {
            return $this->json(['message' => 'Les champs id_bateau et photo sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        $bateau = $this->bateauRepository->find((int) $bateauId);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $bateau->getProprietaire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            return $this->json(['message' => 'Type de fichier non autorisé. Formats acceptés : jpeg, png, webp, gif.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->json(['message' => 'Le fichier ne doit pas dépasser 5 Mo.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $file->move($this->uploadDirectory, $newFilename);

        $photo = new PhotoBateau();
        $photo->setUrl($this->uploadBaseUrl . '/' . $newFilename);
        $photo->setDescription($request->request->get('description'));
        $photo->setOrdreAffichage($request->request->get('ordre_affichage') !== null ? (int) $request->request->get('ordre_affichage') : null);
        $photo->setBateau($bateau);

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

        if (!$this->isGranted('ROLE_ADMIN') && $photo->getBateau()->getProprietaire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        // Supprimer le fichier physique
        $filename = basename($photo->getUrl());
        $filepath = $this->uploadDirectory . '/' . $filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $this->em->remove($photo);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}




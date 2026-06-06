<?php

namespace App\Controller;

use App\Entity\PhotoBateau;
use App\Repository\BateauRepository;
use App\Repository\PhotoBateauRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Photos de bateaux')]
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

    #[OA\Get(
        path: '/api/photos',
        summary: 'Lister toutes les photos',
        responses: [new OA\Response(response: 200, description: 'Liste des photos')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->repository->findAll(), Response::HTTP_OK, [], ['groups' => ['photo:read']]);
    }

    #[OA\Get(
        path: '/api/photos/{id}',
        summary: 'Détail d\'une photo',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Photo trouvée'),
            new OA\Response(response: 404, description: 'Photo non trouvée'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $photo = $this->repository->find($id);

        if (!$photo) {
            return $this->json(['message' => 'Photo non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($photo, Response::HTTP_OK, [], ['groups' => ['photo:read']]);
    }

    #[OA\Post(
        path: '/api/photos',
        summary: 'Uploader une photo de bateau (PROPRIETAIRE, multipart/form-data)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['id_bateau', 'photo'],
                    properties: [
                        new OA\Property(property: 'id_bateau', type: 'integer', example: 1),
                        new OA\Property(property: 'photo', type: 'string', format: 'binary'),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'ordre_affichage', type: 'integer', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Photo uploadée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 422, description: 'Type ou taille invalide'),
        ]
    )]
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

    #[OA\Delete(
        path: '/api/photos/{id}',
        summary: 'Supprimer une photo (propriétaire du bateau ou ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Photo non trouvée'),
        ]
    )]
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




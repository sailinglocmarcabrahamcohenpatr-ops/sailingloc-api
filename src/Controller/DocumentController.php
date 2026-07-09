<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Utilisateur;
use App\Repository\BateauRepository;
use App\Repository\DocumentRepository;
use App\Repository\TypeDocumentRepository;
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

#[OA\Tag(name: 'Documents')]
#[Route('/api/documents', name: 'api_documents_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DocumentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentRepository $repository,
        private readonly TypeDocumentRepository $typeDocumentRepository,
        private readonly BateauRepository $bateauRepository,
        private readonly SluggerInterface $slugger,
        #[Autowire('%upload_documents_directory%')] private readonly string $uploadDirectory,
        #[Autowire('%upload_documents_base_url%')] private readonly string $uploadBaseUrl,
    ) {}

    #[OA\Get(
        path: '/api/documents',
        summary: 'Lister mes documents',
        responses: [new OA\Response(response: 200, description: 'Liste des documents de l\'utilisateur connecté')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $documents = $this->repository->findByUtilisateur($user);

        return $this->json($documents, Response::HTTP_OK, [], ['groups' => ['document:read']]);
    }

    #[OA\Get(
        path: '/api/documents/{id}',
        summary: 'Détail d\'un document',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Document trouvé'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Document non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $document = $this->repository->find($id);

        if (!$document) {
            return $this->json(['message' => 'Document non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$document->getUtilisateurs()->contains($this->getUser())) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($document, Response::HTTP_OK, [], ['groups' => ['document:read']]);
    }

    #[OA\Post(
        path: '/api/documents',
        summary: 'Uploader un document (multipart/form-data)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['fichier', 'id_type_document'],
                    properties: [
                        new OA\Property(property: 'fichier', type: 'string', format: 'binary', description: 'Fichier à uploader (PDF, JPEG, PNG — max 10 Mo)'),
                        new OA\Property(property: 'id_type_document', type: 'integer', example: 1, description: 'ID du type de document'),
                        new OA\Property(property: 'id_bateau', type: 'integer', example: 1, nullable: true, description: 'ID du bateau auquel associer le document (optionnel)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Document uploadé'),
            new OA\Response(response: 400, description: 'Paramètres manquants'),
            new OA\Response(response: 404, description: 'Type de document introuvable'),
            new OA\Response(response: 422, description: 'Type ou taille de fichier invalide'),
        ]
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $file = $request->files->get('fichier');
        $typeDocumentId = $request->request->get('id_type_document');

        if (!$file || !$typeDocumentId) {
            return $this->json(['message' => 'Les champs fichier et id_type_document sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        $typeDocument = $this->typeDocumentRepository->find((int) $typeDocumentId);
        if (!$typeDocument) {
            return $this->json(['message' => 'Type de document introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            return $this->json(['message' => 'Type de fichier non autorisé. Formats acceptés : PDF, JPEG, PNG, WEBP.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['message' => 'Le fichier ne doit pas dépasser 10 Mo.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $file->move($this->uploadDirectory, $newFilename);

        $document = new Document();
        $document->setUrlDocument($this->uploadBaseUrl . '/' . $newFilename);
        $document->setTypeDocument($typeDocument);

        $bateauId = $request->request->get('id_bateau');
        if ($bateauId) {
            $bateau = $this->bateauRepository->find((int) $bateauId);
            if (!$bateau) {
                return $this->json(['message' => 'Bateau introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $document->setBateau($bateau);
        }

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $user->addDocument($document);

        $this->em->persist($document);
        $this->em->flush();

        return $this->json($document, Response::HTTP_CREATED, [], ['groups' => ['document:read']]);
    }

    #[OA\Delete(
        path: '/api/documents/{id}',
        summary: 'Supprimer un document',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Document non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $document = $this->repository->find($id);

        if (!$document) {
            return $this->json(['message' => 'Document non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$document->getUtilisateurs()->contains($this->getUser())) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        // Supprimer le fichier physique
        $filename = basename($document->getUrlDocument());
        $filepath = $this->uploadDirectory . '/' . $filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $this->em->remove($document);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

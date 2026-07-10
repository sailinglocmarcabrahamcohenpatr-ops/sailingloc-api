<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Messages')]
#[Route('/api/messages', name: 'api_messages_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MessageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $repository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[OA\Get(
        path: '/api/messages',
        summary: 'Lister les messages (filtrables par expéditeur/destinataire)',
        parameters: [
            new OA\Parameter(name: 'expediteur', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'destinataire', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des messages')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var \App\Entity\Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $u1 = $request->query->get('expediteur');
        $u2 = $request->query->get('destinataire');

        if ($u1 && $u2 && $this->isGranted('ROLE_ADMIN')) {
            $messages = $this->repository->findConversation((int) $u1, (int) $u2);
        } else {
            $messages = $this->repository->findByUser($currentUser->getId());
        }

        return $this->json($messages, Response::HTTP_OK, [], ['groups' => ['message:read']]);
    }

    #[OA\Get(
        path: '/api/messages/{id}',
        summary: 'Détail d\'un message',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Message trouvé'),
            new OA\Response(response: 404, description: 'Message non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $message = $this->repository->find($id);

        if (!$message) {
            return $this->json(['message' => 'Message non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($message, Response::HTTP_OK, [], ['groups' => ['message:read']]);
    }

    #[OA\Post(
        path: '/api/messages',
        summary: 'Envoyer un message',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['contenu', 'id_destinataire'],
                properties: [
                    new OA\Property(property: 'contenu', type: 'string', example: 'Bonjour, le bateau est-il disponible ?'),
                    new OA\Property(property: 'id_destinataire', type: 'integer', description: 'ID du destinataire', example: 2),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Message envoyé'),
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

        if (empty($data['contenu']) || empty($data['id_destinataire'])) {
            return $this->json(['message' => 'Les champs contenu et id_destinataire sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\Utilisateur $expediteur */
        $expediteur   = $this->getUser();
        $destinataire = $this->utilisateurRepository->find($data['id_destinataire']);

        if (!$destinataire) {
            return $this->json(['message' => 'Destinataire introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($expediteur === $destinataire) {
            return $this->json(['message' => 'Vous ne pouvez pas vous envoyer un message à vous-même.'], Response::HTTP_BAD_REQUEST);
        }

        $message = new Message();
        $message->setContenu($data['contenu']);
        $message->setExpediteur($expediteur);
        $message->setDestinataire($destinataire);

        $errors = $this->validator->validate($message);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($message);
        $this->em->flush();

        return $this->json($message, Response::HTTP_CREATED, [], ['groups' => ['message:read']]);
    }

    #[OA\Patch(
        path: '/api/messages/{id}/lu',
        summary: 'Marquer un message comme lu',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Message marqué comme lu'),
            new OA\Response(response: 404, description: 'Message non trouvé'),
        ]
    )]
    #[Route('/{id}/lu', name: 'marquer_lu', methods: ['PATCH'])]
    public function marquerLu(int $id): JsonResponse
    {
        $message = $this->repository->find($id);

        if (!$message) {
            return $this->json(['message' => 'Message non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $message->setLu(true);
        $this->em->flush();

        return $this->json($message, Response::HTTP_OK, [], ['groups' => ['message:read']]);
    }

    #[OA\Delete(
        path: '/api/messages/{id}',
        summary: 'Supprimer un message',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 404, description: 'Message non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $message = $this->repository->find($id);

        if (!$message) {
            return $this->json(['message' => 'Message non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $message->getExpediteur() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($message);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

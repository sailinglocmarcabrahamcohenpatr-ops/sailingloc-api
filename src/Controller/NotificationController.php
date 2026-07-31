<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Notifications')]
#[Route('/api/notifications', name: 'api_notifications_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $repository,
    ) {}

    #[OA\Get(
        path: '/api/notifications',
        summary: 'Lister les notifications de l\'utilisateur connecté',
        responses: [new OA\Response(response: 200, description: 'Liste des notifications')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->json($this->repository->findByUser($user->getId()), Response::HTTP_OK, [], ['groups' => ['notification:read']]);
    }

    #[OA\Get(
        path: '/api/notifications/{id}',
        summary: 'Détail d\'une notification',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Notification trouvée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Notification non trouvée'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $notification = $this->repository->find($id);

        if (!$notification) {
            return $this->json(['message' => 'Notification non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        if ($notification->getDestinataire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($notification, Response::HTTP_OK, [], ['groups' => ['notification:read']]);
    }

    #[OA\Patch(
        path: '/api/notifications/{id}/lu',
        summary: 'Marquer une notification comme lue',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Notification marquée comme lue'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Notification non trouvée'),
        ]
    )]
    #[Route('/{id}/lu', name: 'marquer_lu', methods: ['PATCH'])]
    public function marquerLu(int $id): JsonResponse
    {
        $notification = $this->repository->find($id);

        if (!$notification) {
            return $this->json(['message' => 'Notification non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        if ($notification->getDestinataire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $notification->setLu(true);
        $this->em->flush();

        return $this->json($notification, Response::HTTP_OK, [], ['groups' => ['notification:read']]);
    }

    #[OA\Delete(
        path: '/api/notifications/{id}',
        summary: 'Supprimer une notification',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Notification non trouvée'),
        ]
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $notification = $this->repository->find($id);

        if (!$notification) {
            return $this->json(['message' => 'Notification non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        if ($notification->getDestinataire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($notification);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

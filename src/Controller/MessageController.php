<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/messages', name: 'api_messages_')]
class MessageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $repository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $u1 = $request->query->get('expediteur');
        $u2 = $request->query->get('destinataire');

        if ($u1 && $u2) {
            $messages = $this->repository->findConversation((int) $u1, (int) $u2);
        } else {
            $messages = $this->repository->findAll();
        }

        return $this->json($messages, Response::HTTP_OK, [], ['groups' => ['message:read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $message = $this->repository->find($id);

        if (!$message) {
            return $this->json(['message' => 'Message non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($message, Response::HTTP_OK, [], ['groups' => ['message:read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['contenu', 'id_utilisateur', 'id_utilisateur_1'];
        $missing = array_filter($required, fn($f) => empty($data[$f]));
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $expediteur = $this->utilisateurRepository->find($data['id_utilisateur']);
        $destinataire = $this->utilisateurRepository->find($data['id_utilisateur_1']);

        if (!$expediteur || !$destinataire) {
            return $this->json(['message' => 'Expéditeur ou destinataire introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = new Message();
        $message->setContenu($data['contenu'] ?? '');
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

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $message = $this->repository->find($id);

        if (!$message) {
            return $this->json(['message' => 'Message non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($message);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Repository\AvisRepository;
use App\Repository\ReservationRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/avis', name: 'api_avis_')]
class AvisController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvisRepository $repository,
        private readonly ReservationRepository $reservationRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->repository->findAll(), Response::HTTP_OK, [], ['groups' => ['avis:read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $avis = $this->repository->find($id);

        if (!$avis) {
            return $this->json(['message' => 'Avis non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($avis, Response::HTTP_OK, [], ['groups' => ['avis:read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['note', 'commentaire', 'id_reservation', 'id_utilisateur'];
        $missing = array_filter($required, fn($f) => !isset($data[$f]) || $data[$f] === '' || $data[$f] === null);
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $reservation = $this->reservationRepository->find($data['id_reservation']);
        $utilisateur = $this->utilisateurRepository->find($data['id_utilisateur']);

        if (!$reservation || !$utilisateur) {
            return $this->json(['message' => 'Réservation ou utilisateur introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $avis = new Avis();
        $avis->setNote((int) ($data['note'] ?? 0));
        $avis->setCommentaire($data['commentaire'] ?? '');
        $avis->setReservation($reservation);
        $avis->setUtilisateur($utilisateur);

        $errors = $this->validator->validate($avis);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($avis);
        $this->em->flush();

        return $this->json($avis, Response::HTTP_CREATED, [], ['groups' => ['avis:read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $avis = $this->repository->find($id);

        if (!$avis) {
            return $this->json(['message' => 'Avis non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($avis);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

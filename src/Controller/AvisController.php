<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Utilisateur;
use App\Repository\AvisRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Avis')]
#[Route('/api/avis', name: 'api_avis_')]
class AvisController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvisRepository $repository,
        private readonly ReservationRepository $reservationRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[OA\Get(
        path: '/api/avis',
        summary: 'Lister tous les avis (ADMIN — modération)',
        responses: [new OA\Response(response: 200, description: 'Liste des avis')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(): JsonResponse
    {
        return $this->json($this->repository->findAll(), Response::HTTP_OK, [], ['groups' => ['avis:read']]);
    }

    #[OA\Get(
        path: '/api/avis/mine',
        summary: 'Lister mes avis (utilisateur connecté)',
        responses: [new OA\Response(response: 200, description: 'Mes avis')]
    )]
    #[Route('/mine', name: 'mine', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function mine(): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->json($this->repository->findByUtilisateur($user), Response::HTTP_OK, [], ['groups' => ['avis:read']]);
    }

    #[OA\Get(
        path: '/api/avis/bateau/{bateauId}',
        summary: 'Lister les avis reçus par un bateau',
        parameters: [new OA\Parameter(name: 'bateauId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Avis du bateau')]
    )]
    #[Route('/bateau/{bateauId}', name: 'by_bateau', methods: ['GET'], requirements: ['bateauId' => '\d+'])]
    public function byBateau(int $bateauId): JsonResponse
    {
        return $this->json($this->repository->findByBateau($bateauId), Response::HTTP_OK, [], ['groups' => ['avis:read']]);
    }

    #[OA\Get(
        path: '/api/avis/{id}',
        summary: 'Détail d\'un avis',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Avis trouvé'),
            new OA\Response(response: 404, description: 'Avis non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $avis = $this->repository->find($id);

        if (!$avis) {
            return $this->json(['message' => 'Avis non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($avis, Response::HTTP_OK, [], ['groups' => ['avis:read']]);
    }

    #[OA\Post(
        path: '/api/avis',
        summary: 'Noter un bateau loué (propriétaire / bateau / lieu, 1 à 5)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['note_proprietaire', 'note_bateau', 'note_lieu', 'commentaire', 'id_reservation'],
                properties: [
                    new OA\Property(property: 'note_proprietaire', type: 'integer', minimum: 1, maximum: 5, example: 5),
                    new OA\Property(property: 'note_bateau', type: 'integer', minimum: 1, maximum: 5, example: 4),
                    new OA\Property(property: 'note_lieu', type: 'integer', minimum: 1, maximum: 5, example: 5),
                    new OA\Property(property: 'commentaire', type: 'string', example: 'Excellent bateau !'),
                    new OA\Property(property: 'id_reservation', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Avis créé'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 403, description: 'Cette réservation ne vous appartient pas'),
            new OA\Response(response: 409, description: 'Réservation déjà notée'),
            new OA\Response(response: 422, description: 'Réservation introuvable ou pas encore terminée'),
        ]
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(Request $request): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['note_proprietaire', 'note_bateau', 'note_lieu', 'commentaire', 'id_reservation'];
        $missing = array_filter($required, fn($f) => !isset($data[$f]) || $data[$f] === '' || $data[$f] === null);
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $reservation = $this->reservationRepository->find($data['id_reservation']);
        if (!$reservation) {
            return $this->json(['message' => 'Réservation introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($reservation->getUtilisateur() !== $user) {
            return $this->json(['message' => 'Cette réservation ne vous appartient pas.'], Response::HTTP_FORBIDDEN);
        }

        if ($reservation->getDateFin() > new \DateTime()) {
            return $this->json(['message' => 'Vous ne pouvez noter cette location qu\'une fois terminée.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->repository->findOneBy(['utilisateur' => $user, 'reservation' => $reservation])) {
            return $this->json(['message' => 'Vous avez déjà noté cette location.'], Response::HTTP_CONFLICT);
        }

        $avis = new Avis();
        $avis->setNoteProprietaire((int) $data['note_proprietaire']);
        $avis->setNoteBateau((int) $data['note_bateau']);
        $avis->setNoteLieu((int) $data['note_lieu']);
        $avis->refreshNoteGlobale();
        $avis->setCommentaire($data['commentaire'] ?? '');
        $avis->setReservation($reservation);
        $avis->setUtilisateur($user);

        $errors = $this->validator->validate($avis);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($avis);
        $this->em->flush();

        return $this->json($avis, Response::HTTP_CREATED, [], ['groups' => ['avis:read']]);
    }

    #[OA\Delete(
        path: '/api/avis/{id}',
        summary: 'Supprimer un avis (son auteur ou un ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Avis non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(int $id): JsonResponse
    {
        $avis = $this->repository->find($id);

        if (!$avis) {
            return $this->json(['message' => 'Avis non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $avis->getUtilisateur() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($avis);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\BateauRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Favoris')]
#[Route('/api/favoris', name: 'api_favoris_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class FavoriteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BateauRepository $bateauRepository,
    ) {}

    #[OA\Get(
        path: '/api/favoris',
        summary: 'Lister les bateaux favoris de l\'utilisateur connecté',
        responses: [new OA\Response(response: 200, description: 'Liste des bateaux favoris')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->json($user->getBateauxFavoris(), Response::HTTP_OK, [], ['groups' => ['bateau:read']]);
    }

    #[OA\Post(
        path: '/api/favoris/{bateauId}',
        summary: 'Ajouter un bateau aux favoris',
        parameters: [new OA\Parameter(name: 'bateauId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Ajouté aux favoris'),
            new OA\Response(response: 404, description: 'Bateau introuvable'),
        ]
    )]
    #[Route('/{bateauId}', name: 'add', methods: ['POST'], requirements: ['bateauId' => '\d+'])]
    public function add(int $bateauId): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $bateau = $this->bateauRepository->find($bateauId);
        if (!$bateau) {
            return $this->json(['message' => 'Bateau introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $user->addBateauFavori($bateau);
        $this->em->flush();

        return $this->json(['favori' => true], Response::HTTP_CREATED);
    }

    #[OA\Delete(
        path: '/api/favoris/{bateauId}',
        summary: 'Retirer un bateau des favoris',
        parameters: [new OA\Parameter(name: 'bateauId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Retiré des favoris'),
            new OA\Response(response: 404, description: 'Bateau introuvable'),
        ]
    )]
    #[Route('/{bateauId}', name: 'remove', methods: ['DELETE'], requirements: ['bateauId' => '\d+'])]
    public function remove(int $bateauId): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $bateau = $this->bateauRepository->find($bateauId);
        if (!$bateau) {
            return $this->json(['message' => 'Bateau introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $user->removeBateauFavori($bateau);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

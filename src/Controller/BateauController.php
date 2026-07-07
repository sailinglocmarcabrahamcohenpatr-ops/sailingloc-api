<?php

namespace App\Controller;

use App\Entity\Bateau;
use App\Enum\StatutBateauEnum;
use App\Repository\BateauRepository;
use App\Repository\PortRepository;
use App\Repository\TypeBateauRepository;
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

#[OA\Tag(name: 'Bateaux')]
#[Route('/api/bateaux', name: 'api_bateaux_')]
class BateauController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BateauRepository $repository,
        private readonly PortRepository $portRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly TypeBateauRepository $typeBateauRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[OA\Get(
        path: '/api/bateaux',
        summary: 'Lister les bateaux',
        parameters: [
            new OA\Parameter(name: 'statut',          in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['disponible', 'loué', 'maintenance', 'en attente de validation'])),
            new OA\Parameter(name: 'proprietaire_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Filtrer par propriétaire'),
            new OA\Parameter(name: 'page',            in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit',           in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste paginée des bateaux')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $statut         = $request->query->get('statut');
        $proprietaireId = $request->query->get('proprietaire_id') ? (int) $request->query->get('proprietaire_id') : null;
        $page           = max(1, (int) $request->query->get('page', 1));
        $limit          = min(100, max(1, (int) $request->query->get('limit', 20)));

        $result = $this->repository->findPaginated($page, $limit, $statut, $proprietaireId);

        return $this->json([
            'data'       => $result['items'],
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'pages' => (int) ceil($result['total'] / max(1, $limit)),
            ],
        ], Response::HTTP_OK, [], ['groups' => ['bateau:read']]);
    }

    #[OA\Get(
        path: '/api/bateaux/{id}',
        summary: 'Détail d\'un bateau',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Bateau trouvé'),
            new OA\Response(response: 404, description: 'Bateau non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $bateau = $this->repository->find($id);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($bateau, Response::HTTP_OK, [], ['groups' => ['bateau:read']]);
    }

    #[OA\Post(
        path: '/api/bateaux',
        summary: 'Créer un bateau (PROPRIETAIRE)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom_bateau', 'motorisation', 'taille', 'prix_jour', 'id_port', 'id_utilisateur', 'id_type_bateau'],
                properties: [
                    new OA\Property(property: 'nom_bateau', type: 'string', example: 'Mon Voilier'),
                    new OA\Property(property: 'motorisation', type: 'string', example: 'voile'),
                    new OA\Property(property: 'taille', type: 'string', example: '12m'),
                    new OA\Property(property: 'prix_jour', type: 'number', example: 250),
                    new OA\Property(property: 'id_port', type: 'integer', example: 1),
                    new OA\Property(property: 'id_utilisateur', type: 'integer', example: 2),
                    new OA\Property(property: 'id_type_bateau', type: 'integer', example: 1),
                    new OA\Property(property: 'capacite', type: 'integer', example: 8, nullable: true),
                    new OA\Property(property: 'avec_skipper', type: 'boolean', example: false),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'statut', type: 'string', enum: ['disponible', 'lou\u00e9', 'maintenance', 'en attente de validation'], example: 'en attente de validation'),
                    new OA\Property(property: 'prix_heure', type: 'number', nullable: true),
                    new OA\Property(property: 'permis_requis', type: 'boolean', example: false),
                    new OA\Property(property: 'nombre_cabines', type: 'integer', nullable: true),
                    new OA\Property(property: 'caution', type: 'number', nullable: true),
                    new OA\Property(property: 'carburant_inclus', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Bateau créé'),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_PROPRIETAIRE')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['nom_bateau', 'motorisation', 'taille', 'prix_jour', 'id_port', 'id_utilisateur', 'id_type_bateau'];
        $missing = array_filter($required, fn($f) => !isset($data[$f]) || $data[$f] === '' || $data[$f] === null);
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $port = $this->portRepository->find($data['id_port']);
        $proprietaire = $this->utilisateurRepository->find($data['id_utilisateur']);
        $typeBateau = $this->typeBateauRepository->find($data['id_type_bateau']);

        if (!$port || !$proprietaire || !$typeBateau) {
            return $this->json(['message' => 'Port, utilisateur ou type de bateau introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $bateau = new Bateau();
        $bateau->setNomBateau($data['nom_bateau'] ?? '');
        $bateau->setMotorisation($data['motorisation'] ?? '');
        $bateau->setCapacite($data['capacite'] ?? null);
        $bateau->setTaille($data['taille'] ?? '');
        $bateau->setAvecSkipper((bool) ($data['avec_skipper'] ?? false));
        $bateau->setDescription($data['description'] ?? null);
        $bateau->setStatut(StatutBateauEnum::from($data['statut'] ?? StatutBateauEnum::INDISPONIBLE->value));
        $bateau->setPrixJour((string) ($data['prix_jour'] ?? '0'));
        $bateau->setPrixHeure(isset($data['prix_heure']) ? (string) $data['prix_heure'] : null);
        $bateau->setPermisRequis((bool) ($data['permis_requis'] ?? false));
        $bateau->setNombreCabines(isset($data['nombre_cabines']) ? (int) $data['nombre_cabines'] : null);
        $bateau->setCaution(isset($data['caution']) ? (string) $data['caution'] : null);
        $bateau->setCarburantInclus((bool) ($data['carburant_inclus'] ?? false));
        $bateau->setPort($port);
        $bateau->setProprietaire($proprietaire);
        $bateau->setTypeBateau($typeBateau);

        $errors = $this->validator->validate($bateau);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($bateau);
        $this->em->flush();

        return $this->json($bateau, Response::HTTP_CREATED, [], ['groups' => ['bateau:read']]);
    }

    #[OA\Put(
        path: '/api/bateaux/{id}',
        summary: 'Modifier un bateau (propriétaire ou ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Bateau mis à jour'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Bateau non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $bateau = $this->repository->find($id);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $bateau->getProprietaire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['nom_bateau'])) $bateau->setNomBateau($data['nom_bateau']);
        if (isset($data['motorisation'])) $bateau->setMotorisation($data['motorisation']);
        if (array_key_exists('capacite', $data)) $bateau->setCapacite($data['capacite']);
        if (isset($data['taille'])) $bateau->setTaille($data['taille']);
        if (isset($data['avec_skipper'])) $bateau->setAvecSkipper((bool) $data['avec_skipper']);
        if (array_key_exists('description', $data)) $bateau->setDescription($data['description']);
        if (isset($data['statut'])) {
            $statut = StatutBateauEnum::tryFrom($data['statut']);
            if ($statut === null) {
                return $this->json(['message' => 'Statut invalide.', 'valeurs' => array_column(StatutBateauEnum::cases(), 'value')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $bateau->setStatut($statut);
        }
        if (isset($data['prix_jour'])) $bateau->setPrixJour((string) $data['prix_jour']);
        if (array_key_exists('prix_heure', $data)) $bateau->setPrixHeure($data['prix_heure'] !== null ? (string) $data['prix_heure'] : null);
        if (isset($data['permis_requis'])) $bateau->setPermisRequis((bool) $data['permis_requis']);
        if (array_key_exists('nombre_cabines', $data)) $bateau->setNombreCabines($data['nombre_cabines'] !== null ? (int) $data['nombre_cabines'] : null);
        if (array_key_exists('caution', $data)) $bateau->setCaution($data['caution'] !== null ? (string) $data['caution'] : null);
        if (isset($data['carburant_inclus'])) $bateau->setCarburantInclus((bool) $data['carburant_inclus']);

        if (isset($data['id_port'])) {
            $port = $this->portRepository->find($data['id_port']);
            if (!$port) return $this->json(['message' => 'Port introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $bateau->setPort($port);
        }

        if (isset($data['id_type_bateau'])) {
            $type = $this->typeBateauRepository->find($data['id_type_bateau']);
            if (!$type) return $this->json(['message' => 'Type de bateau introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $bateau->setTypeBateau($type);
        }

        $errors = $this->validator->validate($bateau);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json($bateau, Response::HTTP_OK, [], ['groups' => ['bateau:read']]);
    }

    #[OA\Delete(
        path: '/api/bateaux/{id}',
        summary: 'Supprimer un bateau (propriétaire ou ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Bateau non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $bateau = $this->repository->find($id);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $bateau->getProprietaire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($bateau);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Get(
        path: '/api/bateaux/{id}/disponibilites',
        summary: 'Disponibilités d\'un bateau',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Liste des disponibilités')]
    )]
    #[Route('/{id}/disponibilites', name: 'disponibilites', methods: ['GET'])]
    public function disponibilites(int $id): JsonResponse
    {
        $bateau = $this->repository->find($id);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($bateau->getDisponibilites(), Response::HTTP_OK, [], ['groups' => ['disponibilite:read']]);
    }

    #[OA\Get(
        path: '/api/bateaux/{id}/photos',
        summary: 'Photos d\'un bateau',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Liste des photos')]
    )]
    #[Route('/{id}/photos', name: 'photos', methods: ['GET'])]
    public function photos(int $id): JsonResponse
    {
        $bateau = $this->repository->find($id);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($bateau->getPhotos(), Response::HTTP_OK, [], ['groups' => ['photo:read']]);
    }

    #[OA\Get(
        path: '/api/bateaux/{id}/reservations',
        summary: 'Réservations d\'un bateau',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Liste des réservations')]
    )]
    #[Route('/{id}/reservations', name: 'reservations', methods: ['GET'])]
    public function reservations(int $id): JsonResponse
    {
        $bateau = $this->repository->find($id);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($bateau->getReservations(), Response::HTTP_OK, [], ['groups' => ['reservation:read']]);
    }
}

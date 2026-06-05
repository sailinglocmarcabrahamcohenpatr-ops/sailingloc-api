<?php

namespace App\Controller;

use App\Entity\Bateau;
use App\Repository\BateauRepository;
use App\Repository\PortRepository;
use App\Repository\TypeBateauRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $statut = $request->query->get('statut');

        if ($statut) {
            $bateaux = $this->repository->findByStatut($statut);
        } else {
            $bateaux = $this->repository->findAll();
        }

        return $this->json($bateaux, Response::HTTP_OK, [], ['groups' => ['bateau:read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $bateau = $this->repository->find($id);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($bateau, Response::HTTP_OK, [], ['groups' => ['bateau:read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
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
        $bateau->setStatut($data['statut'] ?? 'disponible');
        $bateau->setPrixJour((string) ($data['prix_jour'] ?? '0'));
        $bateau->setPrixHeure(isset($data['prix_heure']) ? (string) $data['prix_heure'] : null);
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

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $bateau = $this->repository->find($id);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['nom_bateau'])) $bateau->setNomBateau($data['nom_bateau']);
        if (isset($data['motorisation'])) $bateau->setMotorisation($data['motorisation']);
        if (array_key_exists('capacite', $data)) $bateau->setCapacite($data['capacite']);
        if (isset($data['taille'])) $bateau->setTaille($data['taille']);
        if (isset($data['avec_skipper'])) $bateau->setAvecSkipper((bool) $data['avec_skipper']);
        if (array_key_exists('description', $data)) $bateau->setDescription($data['description']);
        if (isset($data['statut'])) $bateau->setStatut($data['statut']);
        if (isset($data['prix_jour'])) $bateau->setPrixJour((string) $data['prix_jour']);
        if (array_key_exists('prix_heure', $data)) $bateau->setPrixHeure($data['prix_heure'] !== null ? (string) $data['prix_heure'] : null);

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

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $bateau = $this->repository->find($id);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($bateau);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/disponibilites', name: 'disponibilites', methods: ['GET'])]
    public function disponibilites(int $id): JsonResponse
    {
        $bateau = $this->repository->find($id);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($bateau->getDisponibilites(), Response::HTTP_OK, [], ['groups' => ['disponibilite:read']]);
    }

    #[Route('/{id}/photos', name: 'photos', methods: ['GET'])]
    public function photos(int $id): JsonResponse
    {
        $bateau = $this->repository->find($id);

        if (!$bateau) {
            return $this->json(['message' => 'Bateau non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($bateau->getPhotos(), Response::HTTP_OK, [], ['groups' => ['photo:read']]);
    }

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

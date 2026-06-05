<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/utilisateurs', name: 'api_utilisateurs_')]
class UtilisateurController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UtilisateurRepository $repository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $utilisateurs = $this->repository->findAll();

        return $this->json($utilisateurs, Response::HTTP_OK, [], ['groups' => ['utilisateur:read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $utilisateur = $this->repository->find($id);

        if (!$utilisateur) {
            return $this->json(['message' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($utilisateur, Response::HTTP_OK, [], ['groups' => ['utilisateur:read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['nom', 'prenom', 'email', 'password'];
        $missing = array_filter($required, fn($f) => empty($data[$f]));
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $utilisateur = new Utilisateur();
        $utilisateur->setNom($data['nom'] ?? '');
        $utilisateur->setPrenom($data['prenom'] ?? '');
        $utilisateur->setEmail($data['email'] ?? '');
        $utilisateur->setPassword($this->hasher->hashPassword($utilisateur, $data['password'] ?? ''));
        $utilisateur->setTelephone($data['telephone'] ?? null);
        $utilisateur->setStatutCompte($data['statut_compte'] ?? 'actif');

        $errors = $this->validator->validate($utilisateur);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($utilisateur);
        $this->em->flush();

        return $this->json($utilisateur, Response::HTTP_CREATED, [], ['groups' => ['utilisateur:read']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $utilisateur = $this->repository->find($id);

        if (!$utilisateur) {
            return $this->json(['message' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['nom'])) $utilisateur->setNom($data['nom']);
        if (isset($data['prenom'])) $utilisateur->setPrenom($data['prenom']);
        if (isset($data['email'])) $utilisateur->setEmail($data['email']);
        if (isset($data['password'])) $utilisateur->setPassword($this->hasher->hashPassword($utilisateur, $data['password']));
        if (isset($data['telephone'])) $utilisateur->setTelephone($data['telephone']);
        if (isset($data['statut_compte'])) $utilisateur->setStatutCompte($data['statut_compte']);

        $errors = $this->validator->validate($utilisateur);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json($utilisateur, Response::HTTP_OK, [], ['groups' => ['utilisateur:read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $utilisateur = $this->repository->find($id);

        if (!$utilisateur) {
            return $this->json(['message' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($utilisateur);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

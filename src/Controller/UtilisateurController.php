<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\RoleEnum;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Utilisateurs')]
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

    #[OA\Get(
        path: '/api/utilisateurs',
        summary: 'Lister tous les utilisateurs (ADMIN)',
        responses: [
            new OA\Response(response: 200, description: 'Liste des utilisateurs'),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(): JsonResponse
    {
        $utilisateurs = $this->repository->findAll();

        return $this->json($utilisateurs, Response::HTTP_OK, [], ['groups' => ['utilisateur:read']]);
    }

    #[OA\Get(
        path: '/api/utilisateurs/{id}',
        summary: 'Voir le profil d\'un utilisateur',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Profil de l\'utilisateur'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $utilisateur = $this->repository->find($id);

        if (!$utilisateur) {
            return $this->json(['message' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($utilisateur, Response::HTTP_OK, [], ['groups' => ['utilisateur:read']]);
    }

    #[OA\Post(
        path: '/api/utilisateurs',
        summary: 'Créer un utilisateur (ADMIN)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'prenom', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jean@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                    new OA\Property(property: 'telephone', type: 'string', example: '+33612345678', nullable: true),
                    new OA\Property(property: 'statut_compte', type: 'string', example: 'actif', enum: ['actif', 'inactif', 'banni'], nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Utilisateur créé'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
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

    #[OA\Put(
        path: '/api/utilisateurs/{id}',
        summary: 'Modifier un utilisateur (propriétaire ou ADMIN)',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jean@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'nouveauSecret'),
                    new OA\Property(property: 'telephone', type: 'string', example: '+33612345678', nullable: true),
                    new OA\Property(property: 'statut_compte', type: 'string', example: 'actif', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur mis à jour'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $utilisateur = $this->repository->find($id);

        if (!$utilisateur) {
            return $this->json(['message' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $this->getUser() !== $utilisateur) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
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

    #[OA\Delete(
        path: '/api/utilisateurs/{id}',
        summary: 'Supprimer un utilisateur (ADMIN)',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé avec succès'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé'),
        ]
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
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

    #[OA\Patch(
        path: '/api/utilisateurs/{id}/promote-proprietaire',
        summary: 'Promouvoir un utilisateur au rôle PROPRIETAIRE (ADMIN)',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rôle mis à jour'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé'),
        ]
    )]
    #[Route('/{id}/promote-proprietaire', name: 'promote_proprietaire', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function promoteProprietaire(int $id): JsonResponse
    {
        $utilisateur = $this->repository->find($id);

        if (!$utilisateur) {
            return $this->json(['message' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $utilisateur->addRole(RoleEnum::PROPRIETAIRE);
        $this->em->flush();

        return $this->json($utilisateur, Response::HTTP_OK, [], ['groups' => ['utilisateur:read']]);
    }

    #[OA\Get(
        path: '/api/utilisateurs/search/email',
        summary: 'Rechercher un utilisateur par email (ADMIN)',
        parameters: [
            new OA\Parameter(name: 'email', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'email')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur trouvé'),
            new OA\Response(response: 400, description: 'Paramètre email manquant'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé'),
        ]
    )]
    #[Route('/search/email', name: 'search_by_email', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function searchByEmail(Request $request): JsonResponse
    {
        $email = $request->query->get('email');

        if (!$email) {
            return $this->json(['message' => 'Le paramètre email est requis.'], Response::HTTP_BAD_REQUEST);
        }

        $utilisateur = $this->repository->findOneBy(['email' => $email]);

        if (!$utilisateur) {
            return $this->json(['message' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($utilisateur, Response::HTTP_OK, [], ['groups' => ['utilisateur:read']]);
    }
}


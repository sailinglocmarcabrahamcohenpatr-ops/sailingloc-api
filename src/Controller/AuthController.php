<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\StatutCompteEnum;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Authentification')]
#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Connexion et obtention d\'un JWT',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token JWT retourné',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Identifiants invalides'),
        ]
    )]
    #[Security(name: null)]
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): never
    {
        // Interceptée par le firewall json_login avant d'arriver ici
        throw new \LogicException('le firewall n\'est pas configuré correctement pour intercepter cette route.');
    }

    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Créer un nouveau compte utilisateur',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'nom', 'prenom'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'telephone', type: 'string', example: '+33612345678', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé — un email de confirmation a été envoyé'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 409, description: 'Email déjà utilisé'),
        ]
    )]
    #[Security(name: null)]
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        UtilisateurRepository $utilisateurRepository,
        ValidatorInterface $validator,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['email', 'password', 'nom', 'prenom'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Le champ '$field' est requis"], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($utilisateurRepository->findByEmail($data['email'])) {
            return $this->json(['error' => 'Cet email est déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $emailConstraint = new Assert\Email();
        $emailViolations = $validator->validate($data['email'], $emailConstraint);
        if (count($emailViolations) > 0) {
            return $this->json(['error' => 'Email invalide'], Response::HTTP_BAD_REQUEST);
        }

        $token = bin2hex(random_bytes(32));

        $utilisateur = new Utilisateur();
        $utilisateur->setNom($data['nom']);
        $utilisateur->setPrenom($data['prenom']);
        $utilisateur->setEmail($data['email']);
        $utilisateur->setTelephone($data['telephone'] ?? null);
        $utilisateur->setTokenConfirmation($token);

        $hashedPassword = $hasher->hashPassword($utilisateur, $data['password']);
        $utilisateur->setPassword($hashedPassword);

        $em->persist($utilisateur);
        $em->flush();

        $confirmUrl = $urlGenerator->generate(
            'api_auth_confirm',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->to($utilisateur->getEmail())
            ->subject('Confirmez votre compte SailingLoc')
            ->html($this->renderView('emails/confirmation.html.twig', [
                'prenom'      => $utilisateur->getPrenom(),
                'confirmUrl'  => $confirmUrl,
            ]));

        $mailer->send($email);

        return $this->json([
            'message' => 'Compte créé avec succès. Veuillez vérifier vos emails pour activer votre compte.',
            'id'      => $utilisateur->getId(),
            'email'   => $utilisateur->getEmail(),
        ], Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/api/auth/confirm/{token}',
        summary: 'Confirmer l\'adresse email et activer le compte',
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Compte activé avec succès'),
            new OA\Response(response: 404, description: 'Token invalide ou expiré'),
        ]
    )]
    #[Security(name: null)]
    #[Route('/confirm/{token}', name: 'confirm', methods: ['GET'])]
    public function confirm(
        string $token,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $utilisateur = $utilisateurRepository->findOneBy(['tokenConfirmation' => $token]);

        if (!$utilisateur) {
            return $this->json(['error' => 'Token invalide ou expiré.'], Response::HTTP_NOT_FOUND);
        }

        $utilisateur->setStatutCompte(StatutCompteEnum::ACTIF->value);
        $utilisateur->setTokenConfirmation(null);

        $em->flush();

        return $this->json(['message' => 'Votre compte a été activé avec succès. Vous pouvez maintenant vous connecter.']);
    }
}

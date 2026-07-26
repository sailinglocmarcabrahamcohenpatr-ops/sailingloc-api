<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Mercure')]
#[Route('/api/mercure', name: 'api_mercure_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MercureController extends AbstractController
{
    #[OA\Get(
        path: '/api/mercure/token',
        summary: 'Obtenir un cookie d\'autorisation Mercure pour s\'abonner aux topics de l\'utilisateur connecté',
        responses: [
            new OA\Response(response: 200, description: 'Cookie mercureAuthorization posé'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    #[Route('/token', name: 'token', methods: ['GET'])]
    public function token(Authorization $authorization): Response
    {
        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();

        $response = new Response();

        // Le cookie autorise l'utilisateur à s'abonner UNIQUEMENT à ses propres topics
        $authorization->setCookie($response, [
            "/messages/user/{$user->getId()}",
        ]);

        return $response;
    }
}

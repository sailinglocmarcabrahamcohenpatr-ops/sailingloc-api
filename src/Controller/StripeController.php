<?php

namespace App\Controller;

use App\Entity\Paiement;
use App\Enum\StatutPaiementEnum;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use App\Repository\StatutPaiementRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Stripe')]
class StripeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository $reservationRepository,
        private readonly PaiementRepository $paiementRepository,
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private readonly string $stripeSecretKey,
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')]
        private readonly string $stripeWebhookSecret,
    ) {}

    #[OA\Post(
        path: '/api/paiements/stripe/create-intent',
        summary: 'Créer un PaymentIntent Stripe pour une réservation',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_reservation'],
                properties: [
                    new OA\Property(property: 'id_reservation', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'PaymentIntent créé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'client_secret', type: 'string'),
                        new OA\Property(property: 'paiement_id', type: 'integer'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
        ]
    )]
    #[Route('/api/paiements/stripe/create-intent', name: 'api_stripe_create_intent', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function createIntent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['id_reservation'])) {
            return $this->json(['message' => 'Le champ id_reservation est requis.'], Response::HTTP_BAD_REQUEST);
        }

        $reservation = $this->reservationRepository->find($data['id_reservation']);
        if (!$reservation) {
            return $this->json(['message' => 'Réservation introuvable.'], Response::HTTP_NOT_FOUND);
        }

        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();
        if ($reservation->getUtilisateur()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $stripe = new StripeClient($this->stripeSecretKey);

        $montantCentimes = (int) round((float) $reservation->getMontantTotal() * 100);

        $intent = $stripe->paymentIntents->create([
            'amount'   => $montantCentimes,
            'currency' => 'eur',
            'metadata' => ['reservation_id' => $reservation->getId()],
        ]);

        // Statut "en_attente" par défaut
        $paiement = new Paiement();
        $paiement->setMontant($reservation->getMontantTotal());
        $paiement->setReservation($reservation);
        $paiement->setStatutPaiement(StatutPaiementEnum::EN_ATTENTE);
        $paiement->setStripePaymentIntentId($intent->id);

        $this->em->persist($paiement);
        $this->em->flush();

        return $this->json([
            'client_secret' => $intent->client_secret,
            'paiement_id'   => $paiement->getId(),
        ], Response::HTTP_CREATED);
    }

    #[OA\Post(
        path: '/api/stripe/webhook',
        summary: 'Webhook Stripe (usage interne — ne pas appeler directement)',
        responses: [
            new OA\Response(response: 200, description: 'Event traité'),
            new OA\Response(response: 400, description: 'Signature invalide ou payload malformé'),
        ]
    )]
    #[Route('/api/stripe/webhook', name: 'api_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature', '');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
        } catch (SignatureVerificationException) {
            return new Response('Signature invalide.', Response::HTTP_BAD_REQUEST);
        } catch (\UnexpectedValueException) {
            return new Response('Payload invalide.', Response::HTTP_BAD_REQUEST);
        }

        match ($event->type) {
            'payment_intent.succeeded'       => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed'  => $this->handlePaymentFailed($event->data->object),
            default                          => null,
        };

        return new Response('', Response::HTTP_OK);
    }

    private function handlePaymentSucceeded(object $intent): void
    {
        $paiement = $this->paiementRepository->findOneBy(['stripePaymentIntentId' => $intent->id]);
        if (!$paiement) {
            return;
        }

        $paiement->setStatutPaiement(StatutPaiementEnum::PAYE);
        $this->em->flush();
    }

    private function handlePaymentFailed(object $intent): void
    {
        $paiement = $this->paiementRepository->findOneBy(['stripePaymentIntentId' => $intent->id]);
        if (!$paiement) {
            return;
        }

        $paiement->setStatutPaiement(StatutPaiementEnum::ECHOUE);
        $this->em->flush();
    }
}

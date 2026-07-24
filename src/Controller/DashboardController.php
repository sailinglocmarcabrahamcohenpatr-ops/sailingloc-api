<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\OwnerRequestStatusEnum;
use App\Enum\StatutBateauEnum;
use App\Repository\AvisRepository;
use App\Repository\BateauRepository;
use App\Repository\OwnerRequestRepository;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use App\Repository\UtilisateurRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Dashboard')]
#[Route('/api/dashboard', name: 'api_dashboard_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly BateauRepository $bateauRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly PaiementRepository $paiementRepository,
        private readonly AvisRepository $avisRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly OwnerRequestRepository $ownerRequestRepository,
    ) {}

    /** Note moyenne pondérée (par nombre d'avis) sur un ensemble de bateaux. */
    private function noteMoyennePourBateaux(array $bateauIds): array
    {
        $aggregats = $this->avisRepository->findAggregateByBateauIds($bateauIds);

        $sommeNotes = 0.0;
        $nombreAvis = 0;
        foreach ($aggregats as $agg) {
            $sommeNotes += $agg['moyenne'] * $agg['total'];
            $nombreAvis += $agg['total'];
        }

        return [
            'moyenne' => $nombreAvis > 0 ? round($sommeNotes / $nombreAvis, 1) : 0.0,
            'total'   => $nombreAvis,
        ];
    }

    /** Jours réservés / jours disponibles sur [$debut, $fin) pour les bateaux donnés, en pourcentage. */
    private function tauxOccupation(array $bateauIds, \DateTimeInterface $debut, \DateTimeInterface $fin): float
    {
        if (!$bateauIds) {
            return 0.0;
        }

        $reservations = $this->reservationRepository->findChevauchantPeriodePourBateaux($bateauIds, $debut, $fin);

        $joursReserves = 0;
        foreach ($reservations as $reservation) {
            $debutChevauchement = max($reservation->getDateDebut(), $debut);
            $finChevauchement   = min($reservation->getDateFin(), $fin);
            $joursReserves += max(0, $debutChevauchement->diff($finChevauchement)->days);
        }

        $capacite = count($bateauIds) * (int) $debut->diff($fin)->days;

        return $capacite > 0 ? round(($joursReserves / $capacite) * 100, 1) : 0.0;
    }

    #[OA\Get(
        path: '/api/dashboard/proprietaire',
        summary: 'Statistiques du tableau de bord propriétaire (ses propres bateaux)',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistiques du propriétaire connecté',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'nombre_bateaux', type: 'integer'),
                    new OA\Property(property: 'reservations_a_venir', type: 'integer'),
                    new OA\Property(property: 'chiffre_affaires_total', type: 'number'),
                    new OA\Property(property: 'chiffre_affaires_mois_courant', type: 'number'),
                    new OA\Property(property: 'taux_occupation_mois_courant', type: 'number', description: 'Pourcentage (0-100)'),
                    new OA\Property(property: 'note_moyenne', type: 'number'),
                    new OA\Property(property: 'nombre_avis', type: 'integer'),
                ])
            ),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    #[Route('/proprietaire', name: 'proprietaire', methods: ['GET'])]
    public function proprietaire(): JsonResponse
    {
        if (!$this->isGranted('ROLE_PROPRIETAIRE')) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $bateaux   = $this->bateauRepository->findByProprietaire($user->getId());
        $bateauIds = array_map(fn($b) => $b->getId(), $bateaux);

        $debutMois = new \DateTime('first day of this month 00:00:00');
        $finMois   = new \DateTime('first day of next month 00:00:00');

        $notes = $this->noteMoyennePourBateaux($bateauIds);

        return $this->json([
            'nombre_bateaux'                => count($bateauIds),
            'reservations_a_venir'          => $this->reservationRepository->countAVenirPourProprietaire($user->getId()),
            'chiffre_affaires_total'        => (float) $this->paiementRepository->sumMontantPourBateaux($bateauIds),
            'chiffre_affaires_mois_courant' => (float) $this->paiementRepository->sumMontantPourBateaux($bateauIds, $debutMois),
            'taux_occupation_mois_courant'  => $this->tauxOccupation($bateauIds, $debutMois, $finMois),
            'note_moyenne'                  => $notes['moyenne'],
            'nombre_avis'                   => $notes['total'],
        ], Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/api/dashboard/admin',
        summary: 'Statistiques globales de la plateforme (ADMIN)',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistiques globales',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'nombre_utilisateurs', type: 'integer'),
                    new OA\Property(property: 'nombre_bateaux', type: 'integer'),
                    new OA\Property(property: 'nombre_bateaux_en_attente_validation', type: 'integer'),
                    new OA\Property(property: 'nombre_reservations', type: 'integer'),
                    new OA\Property(property: 'nombre_demandes_proprietaire_en_attente', type: 'integer'),
                    new OA\Property(property: 'chiffre_affaires_total', type: 'number'),
                    new OA\Property(property: 'chiffre_affaires_mois_courant', type: 'number'),
                ])
            ),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    #[Route('/admin', name: 'admin', methods: ['GET'])]
    public function admin(): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $debutMois = new \DateTime('first day of this month 00:00:00');

        return $this->json([
            'nombre_utilisateurs'                    => $this->utilisateurRepository->count([]),
            'nombre_bateaux'                          => $this->bateauRepository->count([]),
            'nombre_bateaux_en_attente_validation'    => $this->bateauRepository->count(['statut' => StatutBateauEnum::EN_ATTENTE_VALIDATION]),
            'nombre_reservations'                     => $this->reservationRepository->count([]),
            'nombre_demandes_proprietaire_en_attente' => $this->ownerRequestRepository->count(['status' => OwnerRequestStatusEnum::PENDING]),
            'chiffre_affaires_total'                  => (float) $this->paiementRepository->sumMontantTotal(),
            'chiffre_affaires_mois_courant'           => (float) $this->paiementRepository->sumMontantTotal($debutMois),
        ], Response::HTTP_OK);
    }
}

<?php

namespace App\Controller;

use App\Entity\Bateau;
use App\Entity\Contrat;
use App\Entity\Reservation;
use App\Repository\BateauRepository;
use App\Repository\ContratRepository;
use App\Repository\ReservationRepository;
use App\Repository\StatutReservationRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Réservations')]
#[Route('/api/reservations', name: 'api_reservations_')]
class ReservationController extends AbstractController
{
    /** Doit rester alignée avec SERVICE_FEE_RATE côté frontend (shared/config). */
    private const SERVICE_FEE_RATE = 0.069;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository $repository,
        private readonly BateauRepository $bateauRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly ContratRepository $contratRepository,
        private readonly StatutReservationRepository $statutRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    /** Calcule le montant total (sous-total + frais de service) à partir du prix/jour réel du bateau. */
    private function calculerMontantTotal(Bateau $bateau, \DateTimeInterface $debut, \DateTimeInterface $fin): string
    {
        $jours = max(1, (int) $debut->diff($fin)->days);
        $sousTotal = ((float) $bateau->getPrixJour()) * $jours;
        $fraisService = round($sousTotal * self::SERVICE_FEE_RATE);

        return (string) ($sousTotal + $fraisService);
    }

    #[OA\Get(
        path: '/api/reservations',
        summary: 'Lister les réservations (filtrées par utilisateur courant si non-admin)',
        parameters: [
            new OA\Parameter(name: 'utilisateur', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'bateau', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des réservations')]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $utilisateurId = $request->query->get('utilisateur');
        $bateauId = $request->query->get('bateau');

        // Non-admin : force le filtre sur ses propres réservations
        if (!$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $reservations = $this->repository->findByUtilisateur($currentUser->getId());
        } elseif ($utilisateurId) {
            $reservations = $this->repository->findByUtilisateur((int) $utilisateurId);
        } elseif ($bateauId) {
            $reservations = $this->repository->findByBateau((int) $bateauId);
        } else {
            $reservations = $this->repository->findAll();
        }

        return $this->json($reservations, Response::HTTP_OK, [], ['groups' => ['reservation:read']]);
    }

    #[OA\Get(
        path: '/api/reservations/{id}',
        summary: 'Détail d\'une réservation (propriétaire ou ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Réservation trouvée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $reservation = $this->repository->find($id);

        if (!$reservation) {
            return $this->json(['message' => 'Réservation non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $reservation->getUtilisateur() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($reservation, Response::HTTP_OK, [], ['groups' => ['reservation:read']]);
    }

    #[OA\Post(
        path: '/api/reservations',
        summary: 'Créer une réservation',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['date_debut', 'date_fin', 'id_bateau', 'id_utilisateur', 'id_statut_reservation'],
                properties: [
                    new OA\Property(property: 'date_debut', type: 'string', format: 'date', example: '2026-07-01'),
                    new OA\Property(property: 'date_fin', type: 'string', format: 'date', example: '2026-07-07'),
                    new OA\Property(property: 'id_bateau', type: 'integer', example: 1),
                    new OA\Property(property: 'id_utilisateur', type: 'integer', example: 2),
                    new OA\Property(property: 'id_contrat', type: 'integer', nullable: true, description: 'Optionnel — un contrat par défaut est créé si absent'),
                    new OA\Property(property: 'id_statut_reservation', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Réservation créée'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 409, description: 'Bateau déjà réservé sur cette période'),
        ]
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['date_debut', 'date_fin', 'id_bateau', 'id_utilisateur', 'id_statut_reservation'];
        $missing = array_filter($required, fn($f) => !isset($data[$f]) || $data[$f] === '' || $data[$f] === null);
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $bateau = $this->bateauRepository->find($data['id_bateau']);
        $utilisateur = $this->utilisateurRepository->find($data['id_utilisateur']);
        $statut = $this->statutRepository->find($data['id_statut_reservation']);

        if (!$bateau || !$utilisateur || !$statut) {
            return $this->json(['message' => 'Bateau, utilisateur ou statut introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!empty($data['id_contrat'])) {
            $contrat = $this->contratRepository->find($data['id_contrat']);
            if (!$contrat) {
                return $this->json(['message' => 'Contrat introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } else {
            // Aucun contrat fourni (cas normal du tunnel de réservation, qui n'expose pas
            // encore d'étape de signature) : on en crée un par défaut plutôt que de bloquer la réservation.
            $contrat = new Contrat();
            $contrat->setConditions('Conditions générales de location SailingLoc.');
            $contrat->setAssuranceIncluse(true);
            $this->em->persist($contrat);
        }

        $dateDebut = new \DateTime($data['date_debut']);
        $dateFin = new \DateTime($data['date_fin']);
        if ($dateFin <= $dateDebut) {
            return $this->json(['message' => 'La date de fin doit être postérieure à la date de début.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (count($this->repository->findOverlapping($bateau->getId(), $dateDebut, $dateFin)) > 0) {
            return $this->json(['message' => 'Ce bateau est déjà réservé sur une partie de cette période.'], Response::HTTP_CONFLICT);
        }

        $reservation = new Reservation();
        $reservation->setDateDebut($dateDebut);
        $reservation->setDateFin($dateFin);
        // Le montant est toujours recalculé côté serveur à partir du prix/jour réel du bateau :
        // le montant envoyé par le client n'est jamais fiable (cf. bug prix manipulable).
        $reservation->setMontantTotal($this->calculerMontantTotal($bateau, $dateDebut, $dateFin));
        $reservation->setBateau($bateau);
        $reservation->setUtilisateur($utilisateur);
        $reservation->setContrat($contrat);
        $reservation->setStatutReservation($statut);

        $errors = $this->validator->validate($reservation);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($reservation);
        $this->em->flush();

        return $this->json($reservation, Response::HTTP_CREATED, [], ['groups' => ['reservation:read']]);
    }

    #[OA\Put(
        path: '/api/reservations/{id}',
        summary: 'Modifier une réservation (propriétaire ou ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Mise à jour réussie'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $reservation = $this->repository->find($id);

        if (!$reservation) {
            return $this->json(['message' => 'Réservation non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $reservation->getUtilisateur() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['date_debut'])) $reservation->setDateDebut(new \DateTime($data['date_debut']));
        if (isset($data['date_fin'])) $reservation->setDateFin(new \DateTime($data['date_fin']));
        if (isset($data['montant_total'])) $reservation->setMontantTotal((string) $data['montant_total']);

        if (isset($data['id_statut_reservation'])) {
            $statut = $this->statutRepository->find($data['id_statut_reservation']);
            if (!$statut) return $this->json(['message' => 'Statut introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $reservation->setStatutReservation($statut);
        }

        $errors = $this->validator->validate($reservation);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json($reservation, Response::HTTP_OK, [], ['groups' => ['reservation:read']]);
    }

    #[OA\Delete(
        path: '/api/reservations/{id}',
        summary: 'Annuler une réservation (propriétaire ou ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Non trouvée'),
        ]
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $reservation = $this->repository->find($id);

        if (!$reservation) {
            return $this->json(['message' => 'Réservation non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $reservation->getUtilisateur() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($reservation);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/paiements', name: 'paiements', methods: ['GET'])]
    public function paiements(int $id): JsonResponse
    {
        $reservation = $this->repository->find($id);

        if (!$reservation) {
            return $this->json(['message' => 'Réservation non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($reservation->getPaiements(), Response::HTTP_OK, [], ['groups' => ['paiement:read']]);
    }

    #[Route('/{id}/avis', name: 'avis', methods: ['GET'])]
    public function avis(int $id): JsonResponse
    {
        $reservation = $this->repository->find($id);

        if (!$reservation) {
            return $this->json(['message' => 'Réservation non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($reservation->getAvis(), Response::HTTP_OK, [], ['groups' => ['avis:read']]);
    }
}

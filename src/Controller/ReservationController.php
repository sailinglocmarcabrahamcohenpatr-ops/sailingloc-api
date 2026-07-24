<?php

namespace App\Controller;

use App\Entity\Bateau;
use App\Entity\Contrat;
use App\Entity\Reservation;
use App\Enum\StatutContratEnum;
use App\Repository\BateauRepository;
use App\Repository\ContratRepository;
use App\Repository\ReservationRepository;
use App\Repository\StatutReservationRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

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
        private readonly Environment $twig,
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

        // Non-admin : réservations où l'utilisateur est locataire OU propriétaire du bateau réservé
        if (!$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $reservations = $this->repository->findForUtilisateurOuProprietaire($currentUser->getId());
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

        $user = $this->getUser();
        $estLocataire = $reservation->getUtilisateur() === $user;
        $estProprietaireBateau = $reservation->getBateau()->getProprietaire() === $user;
        if (!$this->isGranted('ROLE_ADMIN') && !$estLocataire && !$estProprietaireBateau) {
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
        summary: 'Modifier une réservation — confirmer/refuser un statut, changer les dates (propriétaire du bateau ou ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Mise à jour réussie'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Non trouvée'),
            new OA\Response(response: 409, description: 'Bateau déjà réservé sur cette période'),
        ]
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $reservation = $this->repository->find($id);

        if (!$reservation) {
            return $this->json(['message' => 'Réservation non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        // Seul le propriétaire du bateau (ou un ADMIN) décide des dates/du statut d'une réservation —
        // le locataire ne peut que l'annuler (DELETE), jamais la modifier lui-même.
        if (!$this->isGranted('ROLE_ADMIN') && $reservation->getBateau()->getProprietaire() !== $this->getUser()) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['date_debut']) || isset($data['date_fin'])) {
            $dateDebut = isset($data['date_debut']) ? new \DateTime($data['date_debut']) : $reservation->getDateDebut();
            $dateFin = isset($data['date_fin']) ? new \DateTime($data['date_fin']) : $reservation->getDateFin();

            if ($dateFin <= $dateDebut) {
                return $this->json(['message' => 'La date de fin doit être postérieure à la date de début.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (count($this->repository->findOverlapping($reservation->getBateau()->getId(), $dateDebut, $dateFin, $reservation->getId())) > 0) {
                return $this->json(['message' => 'Ce bateau est déjà réservé sur une partie de cette période.'], Response::HTTP_CONFLICT);
            }

            $reservation->setDateDebut($dateDebut);
            $reservation->setDateFin($dateFin);
            // Les dates changent : le montant recalculé côté serveur remplace l'ancien, jamais une valeur envoyée par le client.
            $reservation->setMontantTotal($this->calculerMontantTotal($reservation->getBateau(), $dateDebut, $dateFin));
        }

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
        summary: 'Annuler une réservation (son locataire, le propriétaire du bateau, ou ADMIN)',
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

        $user = $this->getUser();
        $estLocataire = $reservation->getUtilisateur() === $user;
        $estProprietaireBateau = $reservation->getBateau()->getProprietaire() === $user;
        // Le locataire annule sa propre réservation ; le propriétaire du bateau refuse/annule
        // une réservation faite sur l'un de ses bateaux — les deux passent par la même action.
        if (!$this->isGranted('ROLE_ADMIN') && !$estLocataire && !$estProprietaireBateau) {
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

    #[OA\Get(
        path: '/api/reservations/{id}/contrat',
        summary: 'Télécharger le contrat de location en PDF (locataire, propriétaire du bateau ou ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'PDF du contrat', content: new OA\MediaType(mediaType: 'application/pdf')),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Réservation ou contrat introuvable'),
        ]
    )]
    #[Route('/{id}/contrat', name: 'contrat_pdf', methods: ['GET'])]
    public function contratPdf(int $id): Response
    {
        $reservation = $this->repository->find($id);

        if (!$reservation) {
            return $this->json(['message' => 'Réservation non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        $estLocataire = $reservation->getUtilisateur() === $user;
        $estProprietaireBateau = $reservation->getBateau()->getProprietaire() === $user;
        if (!$this->isGranted('ROLE_ADMIN') && !$estLocataire && !$estProprietaireBateau) {
            return $this->json(['message' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $contrat = $reservation->getContrat();
        if (!$contrat) {
            return $this->json(['message' => 'Aucun contrat associé à cette réservation.'], Response::HTTP_NOT_FOUND);
        }

        $bateau = $reservation->getBateau();
        $nombreJours = max(1, (int) $reservation->getDateDebut()->diff($reservation->getDateFin())->days);

        $statutLabels = [
            StatutContratEnum::EN_ATTENTE->value => 'En attente de signature',
            StatutContratEnum::SIGNE->value      => 'Signé',
            StatutContratEnum::ANNULE->value     => 'Annulé',
            StatutContratEnum::EXPIRE->value     => 'Expiré',
        ];

        $html = $this->twig->render('contrat/pdf.html.twig', [
            'reservation'        => $reservation,
            'contrat'            => $contrat,
            'bateau'             => $bateau,
            'proprietaire'       => $bateau->getProprietaire(),
            'locataire'          => $reservation->getUtilisateur(),
            'nombreJours'        => $nombreJours,
            'statutContratLabel' => $statutLabels[$contrat->getStatutContrat()->value] ?? $contrat->getStatutContrat()->value,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="contrat-reservation-' . $id . '.pdf"',
        ]);
    }
}

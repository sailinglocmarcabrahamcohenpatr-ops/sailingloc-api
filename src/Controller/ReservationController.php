<?php

namespace App\Controller;

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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/reservations', name: 'api_reservations_')]
class ReservationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository $repository,
        private readonly BateauRepository $bateauRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly ContratRepository $contratRepository,
        private readonly StatutReservationRepository $statutRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $utilisateurId = $request->query->get('utilisateur');
        $bateauId = $request->query->get('bateau');

        if ($utilisateurId) {
            $reservations = $this->repository->findByUtilisateur((int) $utilisateurId);
        } elseif ($bateauId) {
            $reservations = $this->repository->findByBateau((int) $bateauId);
        } else {
            $reservations = $this->repository->findAll();
        }

        return $this->json($reservations, Response::HTTP_OK, [], ['groups' => ['reservation:read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $reservation = $this->repository->find($id);

        if (!$reservation) {
            return $this->json(['message' => 'Réservation non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($reservation, Response::HTTP_OK, [], ['groups' => ['reservation:read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['date_debut', 'date_fin', 'montant_total', 'id_bateau', 'id_utilisateur', 'id_contrat', 'id_statut_reservation'];
        $missing = array_filter($required, fn($f) => !isset($data[$f]) || $data[$f] === '' || $data[$f] === null);
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $bateau = $this->bateauRepository->find($data['id_bateau']);
        $utilisateur = $this->utilisateurRepository->find($data['id_utilisateur']);
        $contrat = $this->contratRepository->find($data['id_contrat']);
        $statut = $this->statutRepository->find($data['id_statut_reservation']);

        if (!$bateau || !$utilisateur || !$contrat || !$statut) {
            return $this->json(['message' => 'Bateau, utilisateur, contrat ou statut introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reservation = new Reservation();
        $reservation->setDateDebut(new \DateTime($data['date_debut']));
        $reservation->setDateFin(new \DateTime($data['date_fin']));
        $reservation->setMontantTotal((string) ($data['montant_total'] ?? '0'));
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

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $reservation = $this->repository->find($id);

        if (!$reservation) {
            return $this->json(['message' => 'Réservation non trouvée.'], Response::HTTP_NOT_FOUND);
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

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $reservation = $this->repository->find($id);

        if (!$reservation) {
            return $this->json(['message' => 'Réservation non trouvée.'], Response::HTTP_NOT_FOUND);
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

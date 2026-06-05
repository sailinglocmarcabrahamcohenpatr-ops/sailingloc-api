<?php

namespace App\Controller;

use App\Entity\Paiement;
use App\Repository\ModeDePaiementRepository;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use App\Repository\StatutPaiementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/paiements', name: 'api_paiements_')]
class PaiementController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaiementRepository $repository,
        private readonly ReservationRepository $reservationRepository,
        private readonly StatutPaiementRepository $statutPaiementRepository,
        private readonly ModeDePaiementRepository $modePaiementRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->repository->findAll(), Response::HTTP_OK, [], ['groups' => ['paiement:read']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $paiement = $this->repository->find($id);

        if (!$paiement) {
            return $this->json(['message' => 'Paiement non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($paiement, Response::HTTP_OK, [], ['groups' => ['paiement:read']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['montant', 'id_reservation', 'id_statut_paiement', 'id_mode_paiement'];
        $missing = array_filter($required, fn($f) => !isset($data[$f]) || $data[$f] === '' || $data[$f] === null);
        if ($missing) {
            return $this->json(['message' => 'Champs obligatoires manquants.', 'champs' => array_values($missing)], Response::HTTP_BAD_REQUEST);
        }

        $reservation = $this->reservationRepository->find($data['id_reservation']);
        $statutRef = $this->statutPaiementRepository->find($data['id_statut_paiement']);
        $mode = $this->modePaiementRepository->find($data['id_mode_paiement']);

        if (!$reservation || !$statutRef || !$mode) {
            return $this->json(['message' => 'Réservation, statut ou mode de paiement introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $paiement = new Paiement();
        $paiement->setMontant((string) ($data['montant'] ?? '0'));
        $paiement->setStatutPaiement($data['statut_paiement'] ?? '');
        $paiement->setReservation($reservation);
        $paiement->setStatutPaiementRef($statutRef);
        $paiement->setModePaiement($mode);

        if (isset($data['date_paiement'])) {
            $paiement->setDatePaiement(new \DateTime($data['date_paiement']));
        }

        $errors = $this->validator->validate($paiement);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($paiement);
        $this->em->flush();

        return $this->json($paiement, Response::HTTP_CREATED, [], ['groups' => ['paiement:read']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $paiement = $this->repository->find($id);

        if (!$paiement) {
            return $this->json(['message' => 'Paiement non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['montant'])) $paiement->setMontant((string) $data['montant']);
        if (isset($data['statut_paiement'])) $paiement->setStatutPaiement($data['statut_paiement']);
        if (isset($data['date_paiement'])) $paiement->setDatePaiement(new \DateTime($data['date_paiement']));

        if (isset($data['id_statut_paiement'])) {
            $statut = $this->statutPaiementRepository->find($data['id_statut_paiement']);
            if (!$statut) return $this->json(['message' => 'Statut introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $paiement->setStatutPaiementRef($statut);
        }

        $errors = $this->validator->validate($paiement);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json($paiement, Response::HTTP_OK, [], ['groups' => ['paiement:read']]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $paiement = $this->repository->find($id);

        if (!$paiement) {
            return $this->json(['message' => 'Paiement non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($paiement);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

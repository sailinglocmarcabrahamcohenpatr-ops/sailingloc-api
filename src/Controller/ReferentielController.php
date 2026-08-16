<?php

namespace App\Controller;

use App\Entity\Equipement;
use App\Entity\TypeEquipement;
use App\Enum\RoleEnum;
use App\Enum\StatutPaiementEnum;
use App\Enum\StatutReservationEnum;
use App\Repository\AssuranceRepository;
use App\Repository\EquipementRepository;
use App\Repository\ModeDePaiementRepository;
use App\Repository\TypeBateauRepository;
use App\Repository\TypeDocumentRepository;
use App\Repository\TypeEquipementRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Référentiels')]
#[Route('/api/referentiels', name: 'api_referentiels_')]
class ReferentielController extends AbstractController
{
    public function __construct(
        private readonly TypeBateauRepository $typeBateauRepository,
        private readonly TypeDocumentRepository $typeDocumentRepository,
        private readonly ModeDePaiementRepository $modePaiementRepository,
        private readonly AssuranceRepository $assuranceRepository,
        private readonly TypeEquipementRepository $typeEquipementRepository,
        private readonly EquipementRepository $equipementRepository,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {}

    #[OA\Get(path: '/api/referentiels/types-bateaux', summary: 'Types de bateaux', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/types-bateaux', name: 'types_bateaux', methods: ['GET'])]
    public function typesBateaux(): JsonResponse
    {
        return $this->json($this->typeBateauRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Get(path: '/api/referentiels/types-documents', summary: 'Types de documents', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/types-documents', name: 'types_documents', methods: ['GET'])]
    public function typesDocuments(): JsonResponse
    {
        return $this->json($this->typeDocumentRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Get(path: '/api/referentiels/roles', summary: 'Rôles disponibles (ROLE_USER, ROLE_PROPRIETAIRE, ROLE_ADMIN)', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/roles', name: 'roles', methods: ['GET'])]
    public function roles(): JsonResponse
    {
        $roles = array_map(fn(RoleEnum $r) => ['value' => $r->value, 'name' => $r->name], RoleEnum::cases());

        return $this->json($roles, Response::HTTP_OK);
    }

    #[OA\Get(path: '/api/referentiels/statuts-reservations', summary: 'Statuts de réservation', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/statuts-reservations', name: 'statuts_reservations', methods: ['GET'])]
    public function statutsReservations(): JsonResponse
    {
        $values = array_map(fn(StatutReservationEnum $s) => ['value' => $s->value], StatutReservationEnum::cases());

        return $this->json($values, Response::HTTP_OK);
    }

    #[OA\Get(path: '/api/referentiels/modes-paiements', summary: 'Modes de paiement', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/modes-paiements', name: 'modes_paiements', methods: ['GET'])]
    public function modesPaiements(): JsonResponse
    {
        return $this->json($this->modePaiementRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Get(path: '/api/referentiels/statuts-paiements', summary: 'Statuts de paiement', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/statuts-paiements', name: 'statuts_paiements', methods: ['GET'])]
    public function statutsPaiements(): JsonResponse
    {
        $values = array_map(fn(StatutPaiementEnum $s) => ['value' => $s->value], StatutPaiementEnum::cases());

        return $this->json($values, Response::HTTP_OK);
    }

    #[OA\Get(path: '/api/referentiels/assurances', summary: 'Types d\'assurances', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/assurances', name: 'assurances', methods: ['GET'])]
    public function assurances(): JsonResponse
    {
        return $this->json($this->assuranceRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Get(path: '/api/referentiels/types-equipements', summary: 'Types d\'équipements (avec leurs équipements)', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/types-equipements', name: 'types_equipements', methods: ['GET'])]
    public function typesEquipements(): JsonResponse
    {
        return $this->json($this->typeEquipementRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Get(path: '/api/referentiels/equipements', summary: 'Liste de tous les équipements', responses: [new OA\Response(response: 200, description: 'OK')])]
    #[Route('/equipements', name: 'equipements', methods: ['GET'])]
    public function equipements(): JsonResponse
    {
        return $this->json($this->equipementRepository->findAll(), Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Post(
        path: '/api/referentiels/types-equipements',
        summary: 'Créer un type d\'équipement (ADMIN)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['label_type_equipement'],
                properties: [
                    new OA\Property(property: 'label_type_equipement', type: 'string', example: 'Sécurité'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Type d\'équipement créé'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 409, description: 'Un type d\'équipement avec ce libellé existe déjà'),
        ]
    )]
    #[Route('/types-equipements', name: 'types_equipements_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createTypeEquipement(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['label_type_equipement'])) {
            return $this->json(['message' => "Le champ 'label_type_equipement' est requis."], Response::HTTP_BAD_REQUEST);
        }

        if ($this->typeEquipementRepository->findOneBy(['labelTypeEquipement' => $data['label_type_equipement']])) {
            return $this->json(['message' => 'Un type d\'équipement avec ce libellé existe déjà.'], Response::HTTP_CONFLICT);
        }

        $typeEquipement = new TypeEquipement();
        $typeEquipement->setLabelTypeEquipement($data['label_type_equipement']);

        $errors = $this->validator->validate($typeEquipement);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($typeEquipement);
        $this->em->flush();

        return $this->json($typeEquipement, Response::HTTP_CREATED, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Put(
        path: '/api/referentiels/types-equipements/{id}',
        summary: 'Modifier un type d\'équipement (ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Type d\'équipement mis à jour'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Type d\'équipement non trouvé'),
            new OA\Response(response: 409, description: 'Un type d\'équipement avec ce libellé existe déjà'),
        ]
    )]
    #[Route('/types-equipements/{id}', name: 'types_equipements_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateTypeEquipement(int $id, Request $request): JsonResponse
    {
        $typeEquipement = $this->typeEquipementRepository->find($id);

        if (!$typeEquipement) {
            return $this->json(['message' => 'Type d\'équipement non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['label_type_equipement'])) {
            $existing = $this->typeEquipementRepository->findOneBy(['labelTypeEquipement' => $data['label_type_equipement']]);
            if ($existing && $existing->getId() !== $typeEquipement->getId()) {
                return $this->json(['message' => 'Un type d\'équipement avec ce libellé existe déjà.'], Response::HTTP_CONFLICT);
            }
            $typeEquipement->setLabelTypeEquipement($data['label_type_equipement']);
        }

        $errors = $this->validator->validate($typeEquipement);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json($typeEquipement, Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Delete(
        path: '/api/referentiels/types-equipements/{id}',
        summary: 'Supprimer un type d\'équipement (ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Type d\'équipement non trouvé'),
            new OA\Response(response: 409, description: 'Des équipements sont encore associés à ce type'),
        ]
    )]
    #[Route('/types-equipements/{id}', name: 'types_equipements_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteTypeEquipement(int $id): JsonResponse
    {
        $typeEquipement = $this->typeEquipementRepository->find($id);

        if (!$typeEquipement) {
            return $this->json(['message' => 'Type d\'équipement non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($typeEquipement);

        try {
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            return $this->json(
                ['message' => 'Impossible de supprimer ce type : des équipements lui sont encore associés.'],
                Response::HTTP_CONFLICT
            );
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Post(
        path: '/api/referentiels/equipements',
        summary: 'Créer un équipement (ADMIN)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'type_equipement_id'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'GPS'),
                    new OA\Property(property: 'icone', type: 'string', example: 'gps-icon', nullable: true),
                    new OA\Property(property: 'type_equipement_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Équipement créé'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Type d\'équipement introuvable'),
            new OA\Response(response: 409, description: 'Un équipement avec ce nom existe déjà'),
        ]
    )]
    #[Route('/equipements', name: 'equipements_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createEquipement(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['nom']) || empty($data['type_equipement_id'])) {
            return $this->json(['message' => "Les champs 'nom' et 'type_equipement_id' sont requis."], Response::HTTP_BAD_REQUEST);
        }

        $typeEquipement = $this->typeEquipementRepository->find($data['type_equipement_id']);
        if (!$typeEquipement) {
            return $this->json(['message' => 'Type d\'équipement introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($this->equipementRepository->findOneBy(['nom' => $data['nom']])) {
            return $this->json(['message' => 'Un équipement avec ce nom existe déjà.'], Response::HTTP_CONFLICT);
        }

        $equipement = new Equipement();
        $equipement->setNom($data['nom']);
        $equipement->setIcone($data['icone'] ?? null);
        $equipement->setTypeEquipement($typeEquipement);

        $errors = $this->validator->validate($equipement);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($equipement);
        $this->em->flush();

        return $this->json($equipement, Response::HTTP_CREATED, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Put(
        path: '/api/referentiels/equipements/{id}',
        summary: 'Modifier un équipement (ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Équipement mis à jour'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Équipement ou type d\'équipement introuvable'),
            new OA\Response(response: 409, description: 'Un équipement avec ce nom existe déjà'),
        ]
    )]
    #[Route('/equipements/{id}', name: 'equipements_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateEquipement(int $id, Request $request): JsonResponse
    {
        $equipement = $this->equipementRepository->find($id);

        if (!$equipement) {
            return $this->json(['message' => 'Équipement non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['nom'])) {
            $existing = $this->equipementRepository->findOneBy(['nom' => $data['nom']]);
            if ($existing && $existing->getId() !== $equipement->getId()) {
                return $this->json(['message' => 'Un équipement avec ce nom existe déjà.'], Response::HTTP_CONFLICT);
            }
            $equipement->setNom($data['nom']);
        }

        if (array_key_exists('icone', $data)) {
            $equipement->setIcone($data['icone']);
        }

        if (isset($data['type_equipement_id'])) {
            $typeEquipement = $this->typeEquipementRepository->find($data['type_equipement_id']);
            if (!$typeEquipement) {
                return $this->json(['message' => 'Type d\'équipement introuvable.'], Response::HTTP_NOT_FOUND);
            }
            $equipement->setTypeEquipement($typeEquipement);
        }

        $errors = $this->validator->validate($equipement);
        if (count($errors) > 0) {
            return $this->json(['message' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json($equipement, Response::HTTP_OK, [], ['groups' => ['referentiel:read']]);
    }

    #[OA\Delete(
        path: '/api/referentiels/equipements/{id}',
        summary: 'Supprimer un équipement (ADMIN)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Équipement non trouvé'),
        ]
    )]
    #[Route('/equipements/{id}', name: 'equipements_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteEquipement(int $id): JsonResponse
    {
        $equipement = $this->equipementRepository->find($id);

        if (!$equipement) {
            return $this->json(['message' => 'Équipement non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($equipement);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

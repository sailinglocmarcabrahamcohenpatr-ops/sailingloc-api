<?php

namespace App\Tests\Controller;

use App\Entity\Avis;
use App\Entity\Reservation;
use App\Enum\RoleEnum;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class AvisControllerTest extends ApiTestCase
{
    private string $userToken;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userToken  = $this->getToken('user.avis@test.com', 'password', RoleEnum::USER);
        $this->adminToken = $this->getToken('admin.avis@test.com', 'password', RoleEnum::ADMIN);
    }

    private function createReservationFixture(bool $terminee = true, ?\App\Entity\Utilisateur $pourUtilisateur = null): Reservation
    {
        $em           = $this->em();
        $proprietaire = $this->createUtilisateur('proprio.avis@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $user         = $pourUtilisateur ?? $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.avis@test.com']);
        $bateau       = $this->createBateau($proprietaire);
        $contrat      = $this->createContrat();
        $statut       = $this->createStatutReservation();

        $resa = new Reservation();
        if ($terminee) {
            $resa->setDateDebut(new \DateTime('2026-07-01'));
            $resa->setDateFin(new \DateTime('2026-07-05'));
        } else {
            $resa->setDateDebut(new \DateTime('+10 days'));
            $resa->setDateFin(new \DateTime('+17 days'));
        }
        $resa->setMontantTotal('500.00');
        $resa->setBateau($bateau);
        $resa->setUtilisateur($user);
        $resa->setContrat($contrat);
        $resa->setStatutReservation($statut);
        $em->persist($resa);
        $em->flush();

        return $resa;
    }

    public function testListEstReserveAuxAdmins(): void
    {
        $this->client->request('GET', '/api/avis', [], [], $this->authHeader($this->adminToken));
        $this->assertResponseIsSuccessful();
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testListRefuseUnSimpleUtilisateur(): void
    {
        $this->client->request('GET', '/api/avis', [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testByBateauResteAccessiblePubliquement(): void
    {
        $resa = $this->createReservationFixture();
        $this->client->request('GET', '/api/avis/bateau/' . $resa->getBateau()->getId());
        $this->assertResponseIsSuccessful();
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testShowInexistant(): void
    {
        $this->client->request('GET', '/api/avis/99999', [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateChampManquant(): void
    {
        $this->client->request(
            'POST', '/api/avis', [], [],
            $this->jsonHeader($this->userToken),
            json_encode(['note_proprietaire' => 5])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCreateSucces(): void
    {
        $resa = $this->createReservationFixture();

        $this->client->request(
            'POST', '/api/avis', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'note_proprietaire' => 5,
                'note_bateau'       => 4,
                'note_lieu'         => 5,
                'commentaire'       => 'Très bonne expérience',
                'id_reservation'    => $resa->getId(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        // moyenne arrondie de (5 + 4 + 5) / 3 = 4.67 -> 5
        $this->assertSame(5, $data['note']);
    }

    public function testCreateReservationInexistante(): void
    {
        $this->client->request(
            'POST', '/api/avis', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'note_proprietaire' => 3,
                'note_bateau'       => 3,
                'note_lieu'         => 3,
                'commentaire'       => 'Test',
                'id_reservation'    => 99999,
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateReservationPasEncoreTerminee(): void
    {
        $resa = $this->createReservationFixture(terminee: false);

        $this->client->request(
            'POST', '/api/avis', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'note_proprietaire' => 4,
                'note_bateau'       => 4,
                'note_lieu'         => 4,
                'commentaire'       => 'Trop tôt',
                'id_reservation'    => $resa->getId(),
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateReservationDuneAutrePersonne(): void
    {
        $autre = $this->createUtilisateur('autre.avis@test.com', 'password', RoleEnum::USER);
        $resa  = $this->createReservationFixture(pourUtilisateur: $autre);

        $this->client->request(
            'POST', '/api/avis', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'note_proprietaire' => 4,
                'note_bateau'       => 4,
                'note_lieu'         => 4,
                'commentaire'       => 'Pas la mienne',
                'id_reservation'    => $resa->getId(),
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateDejaNotee(): void
    {
        $resa = $this->createReservationFixture();
        $payload = json_encode([
            'note_proprietaire' => 4,
            'note_bateau'       => 4,
            'note_lieu'         => 4,
            'commentaire'       => 'Premier avis',
            'id_reservation'    => $resa->getId(),
        ]);

        $this->client->request('POST', '/api/avis', [], [], $this->jsonHeader($this->userToken), $payload);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('POST', '/api/avis', [], [], $this->jsonHeader($this->userToken), $payload);
        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testMineNeRenvoieQueMesAvis(): void
    {
        $resa = $this->createReservationFixture();
        $avis = new Avis();
        $avis->setNoteProprietaire(5);
        $avis->setNoteBateau(5);
        $avis->setNoteLieu(5);
        $avis->refreshNoteGlobale();
        $avis->setCommentaire('Parfait');
        $avis->setReservation($resa);
        $avis->setUtilisateur($resa->getUtilisateur());
        $this->em()->persist($avis);
        $this->em()->flush();

        $this->client->request('GET', '/api/avis/mine', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function testDeleteSucces(): void
    {
        $resa = $this->createReservationFixture();
        $user = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.avis@test.com']);

        $avis = new Avis();
        $avis->setNoteProprietaire(5);
        $avis->setNoteBateau(5);
        $avis->setNoteLieu(5);
        $avis->refreshNoteGlobale();
        $avis->setCommentaire('Super');
        $avis->setReservation($resa);
        $avis->setUtilisateur($user);
        $this->em()->persist($avis);
        $this->em()->flush();

        $this->client->request('DELETE', "/api/avis/{$avis->getId()}", [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}

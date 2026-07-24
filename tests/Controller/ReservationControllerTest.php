<?php

namespace App\Tests\Controller;

use App\Entity\Reservation;
use App\Enum\RoleEnum;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ReservationControllerTest extends ApiTestCase
{
    private string $adminToken;
    private string $userToken;
    private string $autreUserToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminToken    = $this->getToken('admin.resa@test.com', 'password', RoleEnum::ADMIN);
        $this->userToken     = $this->getToken('user.resa@test.com', 'password', RoleEnum::USER);
        $this->autreUserToken = $this->getToken('autre.resa@test.com', 'password', RoleEnum::USER);
    }

    private function createReservationFixture(): Reservation
    {
        $em          = $this->em();
        $proprietaire = $this->createUtilisateur('proprio.resa@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $user         = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.resa@test.com']);
        $bateau       = $this->createBateau($proprietaire);
        $contrat      = $this->createContrat();
        $statut       = $this->createStatutReservation();

        $resa = new Reservation();
        $resa->setDateDebut(new \DateTime('2026-07-01'));
        $resa->setDateFin(new \DateTime('2026-07-07'));
        $resa->setMontantTotal('1050.00');
        $resa->setBateau($bateau);
        $resa->setUtilisateur($user);
        $resa->setContrat($contrat);
        $resa->setStatutReservation($statut);
        $em->persist($resa);
        $em->flush();

        return $resa;
    }

    public function testListRetourneSesPropresReservations(): void
    {
        $this->createReservationFixture();
        $this->client->request('GET', '/api/reservations', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        foreach ($data as $r) {
            $this->assertArrayHasKey('id', $r);
        }
    }

    public function testListAdminVoitTout(): void
    {
        $this->createReservationFixture();
        $this->client->request('GET', '/api/reservations', [], [], $this->authHeader($this->adminToken));
        $this->assertResponseIsSuccessful();
    }

    public function testShowRefuseSiAutreUtilisateur(): void
    {
        $resa = $this->createReservationFixture();
        $this->client->request('GET', "/api/reservations/{$resa->getId()}", [], [], $this->authHeader($this->autreUserToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testShowSuccesSiProprietaireReservation(): void
    {
        $resa = $this->createReservationFixture();
        $this->client->request('GET', "/api/reservations/{$resa->getId()}", [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
    }

    public function testShowInexistant(): void
    {
        $this->client->request('GET', '/api/reservations/99999', [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateChampManquant(): void
    {
        $this->client->request(
            'POST', '/api/reservations', [], [],
            $this->jsonHeader($this->userToken),
            json_encode(['date_debut' => '2026-08-01'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCreateSucces(): void
    {
        $em          = $this->em();
        $proprietaire = $this->createUtilisateur('proprio.resa2@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $user         = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.resa@test.com']);
        $bateau       = $this->createBateau($proprietaire);
        $contrat      = $this->createContrat();
        $statut       = $this->createStatutReservation();

        $this->client->request(
            'POST', '/api/reservations', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'date_debut'           => '2026-09-01',
                'date_fin'             => '2026-09-07',
                'montant_total'        => '1200',
                'id_bateau'            => $bateau->getId(),
                'id_utilisateur'       => $user->getId(),
                'id_contrat'           => $contrat->getId(),
                'id_statut_reservation' => $statut->getId(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testDeleteRefuseSiAutreUtilisateur(): void
    {
        $resa = $this->createReservationFixture();
        $this->client->request('DELETE', "/api/reservations/{$resa->getId()}", [], [], $this->authHeader($this->autreUserToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteSuccesSiAdmin(): void
    {
        $resa = $this->createReservationFixture();
        $this->client->request('DELETE', "/api/reservations/{$resa->getId()}", [], [], $this->authHeader($this->adminToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    // ------------------------------------------------------------------ id_contrat optionnel (bug bloquant corrigé)

    public function testCreateSansIdContratCreeUnContratParDefaut(): void
    {
        $em          = $this->em();
        $proprietaire = $this->createUtilisateur('proprio.resa3@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $user         = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.resa@test.com']);
        $bateau       = $this->createBateau($proprietaire);
        $statut       = $this->createStatutReservation();

        $this->client->request(
            'POST', '/api/reservations', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'date_debut'            => '2026-10-01',
                'date_fin'              => '2026-10-08',
                'id_bateau'             => $bateau->getId(),
                'id_utilisateur'        => $user->getId(),
                'id_statut_reservation' => $statut->getId(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateRecalculeLeMontantDepuisLePrixReelDuBateau(): void
    {
        $em          = $this->em();
        $proprietaire = $this->createUtilisateur('proprio.resa4@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $user         = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.resa@test.com']);
        $bateau       = $this->createBateau($proprietaire); // prix_jour = 150.00 (cf. ApiTestCase::createBateau)
        $statut       = $this->createStatutReservation();

        $this->client->request(
            'POST', '/api/reservations', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'date_debut'            => '2026-11-01',
                'date_fin'              => '2026-11-08', // 7 jours
                'montant_total'         => '1', // valeur volontairement absurde, doit être ignorée
                'id_bateau'             => $bateau->getId(),
                'id_utilisateur'        => $user->getId(),
                'id_statut_reservation' => $statut->getId(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        // 150 * 7 = 1050, + 6.9% de frais de service = 1121 (arrondi)
        $this->assertEquals(1121.0, (float) $data['montantTotal']);
    }

    public function testCreateRefuseChevauchementDeDates(): void
    {
        $em          = $this->em();
        $proprietaire = $this->createUtilisateur('proprio.resa5@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $user         = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.resa@test.com']);
        $autre        = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'autre.resa@test.com']);
        $bateau       = $this->createBateau($proprietaire);
        $statut       = $this->createStatutReservation();

        $this->client->request(
            'POST', '/api/reservations', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'date_debut'            => '2026-12-01',
                'date_fin'              => '2026-12-10',
                'id_bateau'             => $bateau->getId(),
                'id_utilisateur'        => $user->getId(),
                'id_statut_reservation' => $statut->getId(),
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Chevauchement partiel avec la réservation précédente (01-10 décembre)
        $this->client->request(
            'POST', '/api/reservations', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'date_debut'            => '2026-12-05',
                'date_fin'              => '2026-12-15',
                'id_bateau'             => $bateau->getId(),
                'id_utilisateur'        => $autre->getId(),
                'id_statut_reservation' => $statut->getId(),
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }
}

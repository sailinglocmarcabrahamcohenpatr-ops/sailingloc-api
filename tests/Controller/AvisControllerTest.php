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

    private function createReservationFixture(): Reservation
    {
        $em           = $this->em();
        $proprietaire = $this->createUtilisateur('proprio.avis@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $user         = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.avis@test.com']);
        $bateau       = $this->createBateau($proprietaire);
        $contrat      = $this->createContrat();
        $statut       = $this->createStatutReservation();

        $resa = new Reservation();
        $resa->setDateDebut(new \DateTime('2026-07-01'));
        $resa->setDateFin(new \DateTime('2026-07-05'));
        $resa->setMontantTotal('500.00');
        $resa->setBateau($bateau);
        $resa->setUtilisateur($user);
        $resa->setContrat($contrat);
        $resa->setStatutReservation($statut);
        $em->persist($resa);
        $em->flush();

        return $resa;
    }

    public function testListEstAccessible(): void
    {
        $this->client->request('GET', '/api/avis', [], [], $this->authHeader($this->userToken));
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
            json_encode(['note' => 5])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCreateSucces(): void
    {
        $resa = $this->createReservationFixture();
        $user = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.avis@test.com']);

        $this->client->request(
            'POST', '/api/avis', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'note'           => 4,
                'commentaire'    => 'Très bonne expérience',
                'id_reservation' => $resa->getId(),
                'id_utilisateur' => $user->getId(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateReservationInexistante(): void
    {
        $user = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.avis@test.com']);

        $this->client->request(
            'POST', '/api/avis', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'note'           => 3,
                'commentaire'    => 'Test',
                'id_reservation' => 99999,
                'id_utilisateur' => $user->getId(),
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDeleteSucces(): void
    {
        $resa = $this->createReservationFixture();
        $user = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.avis@test.com']);

        $avis = new Avis();
        $avis->setNote(5);
        $avis->setCommentaire('Super');
        $avis->setReservation($resa);
        $avis->setUtilisateur($user);
        $this->em()->persist($avis);
        $this->em()->flush();

        $this->client->request('DELETE', "/api/avis/{$avis->getId()}", [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}

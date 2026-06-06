<?php

namespace App\Tests\Controller;

use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Enum\RoleEnum;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class PaiementControllerTest extends ApiTestCase
{
    private string $userToken;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userToken  = $this->getToken('user.paie@test.com', 'password', RoleEnum::USER);
        $this->adminToken = $this->getToken('admin.paie@test.com', 'password', RoleEnum::ADMIN);
    }

    private function createReservationFixture(): Reservation
    {
        $em           = $this->em();
        $proprietaire = $this->createUtilisateur('proprio.paie@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $user         = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.paie@test.com']);
        $bateau       = $this->createBateau($proprietaire);
        $contrat      = $this->createContrat();
        $statut       = $this->createStatutReservation();

        $resa = new Reservation();
        $resa->setDateDebut(new \DateTime('2026-08-01'));
        $resa->setDateFin(new \DateTime('2026-08-07'));
        $resa->setMontantTotal('800.00');
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
        $this->client->request('GET', '/api/paiements', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testShowInexistant(): void
    {
        $this->client->request('GET', '/api/paiements/99999', [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateChampManquant(): void
    {
        $this->client->request(
            'POST', '/api/paiements', [], [],
            $this->jsonHeader($this->userToken),
            json_encode(['montant' => '100'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCreateSucces(): void
    {
        $resa         = $this->createReservationFixture();
        $statutPaie   = $this->createStatutPaiement();
        $modePaie     = $this->createModePaiement();

        $this->client->request(
            'POST', '/api/paiements', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'montant'            => '800',
                'statut_paiement'    => 'en_attente',
                'id_reservation'     => $resa->getId(),
                'id_statut_paiement' => $statutPaie->getId(),
                'id_mode_paiement'   => $modePaie->getId(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateReservationInexistante(): void
    {
        $statutPaie = $this->createStatutPaiement();
        $modePaie   = $this->createModePaiement();

        $this->client->request(
            'POST', '/api/paiements', [], [],
            $this->jsonHeader($this->userToken),
            json_encode([
                'montant'            => '100',
                'statut_paiement'    => 'en_attente',
                'id_reservation'     => 99999,
                'id_statut_paiement' => $statutPaie->getId(),
                'id_mode_paiement'   => $modePaie->getId(),
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateSucces(): void
    {
        $resa       = $this->createReservationFixture();
        $statutPaie = $this->createStatutPaiement();
        $modePaie   = $this->createModePaiement();

        $paiement = new Paiement();
        $paiement->setMontant('500.00');
        $paiement->setStatutPaiement('en_attente');
        $paiement->setReservation($resa);
        $paiement->setStatutPaiementRef($statutPaie);
        $paiement->setModePaiement($modePaie);
        $this->em()->persist($paiement);
        $this->em()->flush();

        $this->client->request(
            'PATCH', "/api/paiements/{$paiement->getId()}", [], [],
            $this->jsonHeader($this->adminToken),
            json_encode(['montant' => '600', 'statut_paiement' => 'payé'])
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('600', $data['montant']);
    }

    public function testDeleteSucces(): void
    {
        $resa       = $this->createReservationFixture();
        $statutPaie = $this->createStatutPaiement();
        $modePaie   = $this->createModePaiement();

        $paiement = new Paiement();
        $paiement->setMontant('200.00');
        $paiement->setStatutPaiement('en_attente');
        $paiement->setReservation($resa);
        $paiement->setStatutPaiementRef($statutPaie);
        $paiement->setModePaiement($modePaie);
        $this->em()->persist($paiement);
        $this->em()->flush();

        $this->client->request('DELETE', "/api/paiements/{$paiement->getId()}", [], [], $this->authHeader($this->adminToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}

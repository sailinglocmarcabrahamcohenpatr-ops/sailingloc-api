<?php

namespace App\Tests\Controller;

use App\Entity\Disponibilite;
use App\Enum\RoleEnum;
use App\Enum\StatutDisponibiliteEnum;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class DisponibiliteControllerTest extends ApiTestCase
{
    private string $proprietaireToken;
    private string $autreProprietaireToken;
    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proprietaireToken      = $this->getToken('proprio.dispo@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $this->autreProprietaireToken = $this->getToken('autre.proprio.dispo@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $this->userToken              = $this->getToken('user.dispo@test.com', 'password', RoleEnum::USER);
    }

    private function createDispoFixture(): Disponibilite
    {
        $em           = $this->em();
        $proprietaire = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'proprio.dispo@test.com']);
        $bateau       = $this->createBateau($proprietaire);

        $dispo = new Disponibilite();
        $dispo->setDateDebut(new \DateTime('2026-07-01'));
        $dispo->setDateFin(new \DateTime('2026-07-31'));
        $dispo->setStatut(StatutDisponibiliteEnum::DISPONIBLE);
        $dispo->setBateau($bateau);
        $em->persist($dispo);
        $em->flush();

        return $dispo;
    }

    public function testListEstAccessible(): void
    {
        $this->client->request('GET', '/api/disponibilites', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testShowInexistant(): void
    {
        $this->client->request('GET', '/api/disponibilites/99999', [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateRefuseSiUserSimple(): void
    {
        $this->client->request(
            'POST', '/api/disponibilites', [], [],
            $this->jsonHeader($this->userToken),
            json_encode(['date_debut' => '2026-08-01', 'id_bateau' => 1])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateRefuseSiPasProprietaireDuBateau(): void
    {
        $em           = $this->em();
        $proprietaire = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'proprio.dispo@test.com']);
        $bateau       = $this->createBateau($proprietaire);

        $this->client->request(
            'POST', '/api/disponibilites', [], [],
            $this->jsonHeader($this->autreProprietaireToken),
            json_encode(['date_debut' => '2026-08-01', 'id_bateau' => $bateau->getId()])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateSuccesSiProprietaireDuBateau(): void
    {
        $em           = $this->em();
        $proprietaire = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'proprio.dispo@test.com']);
        $bateau       = $this->createBateau($proprietaire);

        $this->client->request(
            'POST', '/api/disponibilites', [], [],
            $this->jsonHeader($this->proprietaireToken),
            json_encode([
                'date_debut' => '2026-10-01',
                'date_fin'   => '2026-10-31',
                'statut'     => 'disponible',
                'id_bateau'  => $bateau->getId(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testUpdateRefuseSiAutreProprietaire(): void
    {
        $dispo = $this->createDispoFixture();
        $this->client->request(
            'PATCH', "/api/disponibilites/{$dispo->getId()}", [], [],
            $this->jsonHeader($this->autreProprietaireToken),
            json_encode(['statut' => 'indisponible'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUpdateSuccesSiProprietaireDuBateau(): void
    {
        $dispo = $this->createDispoFixture();
        $this->client->request(
            'PATCH', "/api/disponibilites/{$dispo->getId()}", [], [],
            $this->jsonHeader($this->proprietaireToken),
            json_encode(['statut' => 'indisponible'])
        );
        $this->assertResponseIsSuccessful();
    }

    public function testDeleteRefuseSiAutreProprietaire(): void
    {
        $dispo = $this->createDispoFixture();
        $this->client->request('DELETE', "/api/disponibilites/{$dispo->getId()}", [], [], $this->authHeader($this->autreProprietaireToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteSuccesSiProprietaireDuBateau(): void
    {
        $dispo = $this->createDispoFixture();
        $this->client->request('DELETE', "/api/disponibilites/{$dispo->getId()}", [], [], $this->authHeader($this->proprietaireToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    // ------------------------------------------------------------------ statut : string -> enum (bug de crash corrigé)

    public function testCreateAvecStatutInvalideRenvoie422EtNePlanteJamais(): void
    {
        $em           = $this->em();
        $proprietaire = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'proprio.dispo@test.com']);
        $bateau       = $this->createBateau($proprietaire);

        $this->client->request(
            'POST', '/api/disponibilites', [], [],
            $this->jsonHeader($this->proprietaireToken),
            json_encode([
                'date_debut' => '2026-09-01',
                'statut'     => 'valeur_qui_nexiste_pas',
                'id_bateau'  => $bateau->getId(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateAvecStatutInvalideRenvoie422EtNePlanteJamais(): void
    {
        $dispo = $this->createDispoFixture();
        $this->client->request(
            'PATCH', "/api/disponibilites/{$dispo->getId()}", [], [],
            $this->jsonHeader($this->proprietaireToken),
            json_encode(['statut' => 'valeur_qui_nexiste_pas'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

<?php

namespace App\Tests\Controller;

use App\Enum\RoleEnum;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class BateauControllerTest extends ApiTestCase
{
    private string $proprietaireToken;
    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proprietaireToken = $this->getToken('proprietaire@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $this->userToken         = $this->getToken('user@test.com', 'password', RoleEnum::USER);
    }

    // ------------------------------------------------------------------ tests

    public function testListBateauxEstAccessibleSansRole(): void
    {
        $this->client->request('GET', '/api/bateaux', [], [], $this->authHeader($this->userToken));

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testCreateBateauRefusesSiUserSimple(): void
    {
        $this->client->request(
            'POST', '/api/bateaux', [], [],
            $this->jsonHeader($this->userToken),
            json_encode(['nom_bateau' => 'Test'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateBateauRefusesSiChampManquant(): void
    {
        $this->client->request(
            'POST', '/api/bateaux', [], [],
            $this->jsonHeader($this->proprietaireToken),
            json_encode(['nom_bateau' => 'Sans champs requis'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('champs', $data);
    }

    public function testCreateBateauAvecPortInexistant(): void
    {
        $this->client->request(
            'POST', '/api/bateaux', [], [],
            $this->jsonHeader($this->proprietaireToken),
            json_encode([
                'nom_bateau'     => 'Mon Bateau',
                'motorisation'   => 'Voile',
                'taille'         => '10m',
                'prix_jour'      => '150',
                'id_port'        => 99999,
                'id_utilisateur' => 1,
                'id_type_bateau' => 1,
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateBateauSucces(): void
    {
        $port  = $this->createPort();
        $type  = $this->createTypeBateau();
        $user  = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'proprietaire@test.com']);

        $this->client->request(
            'POST', '/api/bateaux', [], [],
            $this->jsonHeader($this->proprietaireToken),
            json_encode([
                'nom_bateau'     => 'Mon Voilier',
                'motorisation'   => 'Voile',
                'taille'         => '12m',
                'prix_jour'      => '200',
                'avec_skipper'   => false,
                'statut'         => 'disponible',
                'id_port'        => $port->getId(),
                'id_utilisateur' => $user->getId(),
                'id_type_bateau' => $type->getId(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Mon Voilier', $data['nomBateau']);
    }

    public function testShowBateauInexistant(): void
    {
        $this->client->request('GET', '/api/bateaux/99999', [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteBateauRefuseSiPasProprietaire(): void
    {
        $proprietaire = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'proprietaire@test.com']);
        $bateau       = $this->createBateau($proprietaire);

        $this->client->request('DELETE', "/api/bateaux/{$bateau->getId()}", [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteBateauSuccesSiProprietaire(): void
    {
        $proprietaire = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'proprietaire@test.com']);
        $bateau       = $this->createBateau($proprietaire);

        $this->client->request('DELETE', "/api/bateaux/{$bateau->getId()}", [], [], $this->authHeader($this->proprietaireToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}


<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ReferentielControllerTest extends ApiTestCase
{
    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userToken = $this->getToken('user.ref@test.com', 'password');
    }

    public function testRolesRetourneListeEnum(): void
    {
        $this->client->request('GET', '/api/referentiels/roles', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('value', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
    }

    public function testTypesBateauxRetourneListe(): void
    {
        $this->createTypeBateau('Catamaran');
        $this->client->request('GET', '/api/referentiels/types-bateaux', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testStatutsReservationsRetourneListe(): void
    {
        $this->createStatutReservation('Confirmée');
        $this->client->request('GET', '/api/referentiels/statuts-reservations', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testModesPaiementsRetourneListe(): void
    {
        $this->createModePaiement('Virement');
        $this->client->request('GET', '/api/referentiels/modes-paiements', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testStatutsPaiementsRetourneListe(): void
    {
        $this->createStatutPaiement('Payé');
        $this->client->request('GET', '/api/referentiels/statuts-paiements', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testRoutesRefusesSiNonAuthentifie(): void
    {
        $routes = [
            '/api/referentiels/roles',
            '/api/referentiels/types-bateaux',
            '/api/referentiels/statuts-reservations',
        ];

        foreach ($routes as $route) {
            $this->client->request('GET', $route);
            $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED, "Route $route devrait renvoyer 401");
        }
    }
}

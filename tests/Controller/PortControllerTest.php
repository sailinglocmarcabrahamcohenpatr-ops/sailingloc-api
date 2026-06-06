<?php

namespace App\Tests\Controller;

use App\Enum\RoleEnum;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class PortControllerTest extends ApiTestCase
{
    private string $adminToken;
    private string $proprietaireToken;
    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminToken        = $this->getToken('admin.port@test.com', 'password', RoleEnum::ADMIN);
        $this->proprietaireToken = $this->getToken('proprio.port@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $this->userToken         = $this->getToken('user.port@test.com', 'password', RoleEnum::USER);
    }

    public function testListEstAccessible(): void
    {
        $this->client->request('GET', '/api/ports', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testShowInexistant(): void
    {
        $this->client->request('GET', '/api/ports/99999', [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateRefuseSiUserSimple(): void
    {
        $this->client->request(
            'POST', '/api/ports', [], [],
            $this->jsonHeader($this->userToken),
            json_encode(['nom' => 'Port', 'pays' => 'France', 'ville' => 'Nice'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateSuccesSiProprietaire(): void
    {
        $this->client->request(
            'POST', '/api/ports', [], [],
            $this->jsonHeader($this->proprietaireToken),
            json_encode(['nom' => 'Port Nouveau ' . uniqid(), 'pays' => 'France', 'ville' => 'Nice'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateChampManquant(): void
    {
        $this->client->request(
            'POST', '/api/ports', [], [],
            $this->jsonHeader($this->proprietaireToken),
            json_encode(['nom' => 'Port sans pays'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testUpdateRefuseSiNonAdmin(): void
    {
        $port = $this->createPort('Port Update Test');
        $this->client->request(
            'PATCH', "/api/ports/{$port->getId()}", [], [],
            $this->jsonHeader($this->proprietaireToken),
            json_encode(['nom' => 'Nouveau nom'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUpdateSuccesSiAdmin(): void
    {
        $port = $this->createPort('Port Admin Update ' . uniqid());
        $this->client->request(
            'PATCH', "/api/ports/{$port->getId()}", [], [],
            $this->jsonHeader($this->adminToken),
            json_encode(['nom' => 'Port Modifié'])
        );
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Port Modifié', $data['nom']);
    }

    public function testDeleteRefuseSiNonAdmin(): void
    {
        $port = $this->createPort('Port Delete Refuse ' . uniqid());
        $this->client->request('DELETE', "/api/ports/{$port->getId()}", [], [], $this->authHeader($this->proprietaireToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteSuccesSiAdmin(): void
    {
        $port = $this->createPort('Port Delete OK ' . uniqid());
        $this->client->request('DELETE', "/api/ports/{$port->getId()}", [], [], $this->authHeader($this->adminToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}

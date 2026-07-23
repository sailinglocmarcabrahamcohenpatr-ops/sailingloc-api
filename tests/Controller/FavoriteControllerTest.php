<?php

namespace App\Tests\Controller;

use App\Enum\RoleEnum;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class FavoriteControllerTest extends ApiTestCase
{
    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userToken = $this->getToken('user.favoris@test.com', 'password', RoleEnum::USER);
    }

    public function testListVideParDefaut(): void
    {
        $this->client->request('GET', '/api/favoris', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $this->assertSame([], json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testListNecessiteAuthentification(): void
    {
        $this->client->request('GET', '/api/favoris');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAjouterPuisListerUnFavori(): void
    {
        $proprietaire = $this->createUtilisateur('proprio.favoris@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $bateau = $this->createBateau($proprietaire);

        $this->client->request('POST', "/api/favoris/{$bateau->getId()}", [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('GET', '/api/favoris', [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame($bateau->getId(), $data[0]['id']);
    }

    public function testAjouterDeuxFoisResteIdempotent(): void
    {
        $proprietaire = $this->createUtilisateur('proprio.favoris2@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $bateau = $this->createBateau($proprietaire);

        $this->client->request('POST', "/api/favoris/{$bateau->getId()}", [], [], $this->authHeader($this->userToken));
        $this->client->request('POST', "/api/favoris/{$bateau->getId()}", [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('GET', '/api/favoris', [], [], $this->authHeader($this->userToken));
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
    }

    public function testAjouterBateauInexistant(): void
    {
        $this->client->request('POST', '/api/favoris/999999', [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRetirerUnFavori(): void
    {
        $proprietaire = $this->createUtilisateur('proprio.favoris3@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $bateau = $this->createBateau($proprietaire);

        $this->client->request('POST', "/api/favoris/{$bateau->getId()}", [], [], $this->authHeader($this->userToken));
        $this->client->request('DELETE', "/api/favoris/{$bateau->getId()}", [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('GET', '/api/favoris', [], [], $this->authHeader($this->userToken));
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(0, $data);
    }
}

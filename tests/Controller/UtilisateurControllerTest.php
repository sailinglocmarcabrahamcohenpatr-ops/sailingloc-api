<?php

namespace App\Tests\Controller;

use App\Enum\RoleEnum;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class UtilisateurControllerTest extends ApiTestCase
{
    private string $adminToken;
    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminToken = $this->getToken('admin.util@test.com', 'password', RoleEnum::ADMIN);
        $this->userToken  = $this->getToken('user.util@test.com', 'password', RoleEnum::USER);
    }

    public function testListRefuseSiNonAdmin(): void
    {
        $this->client->request('GET', '/api/utilisateurs', [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testListSuccesSiAdmin(): void
    {
        $this->client->request('GET', '/api/utilisateurs', [], [], $this->authHeader($this->adminToken));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testShowSonPropreProfil(): void
    {
        $user = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.util@test.com']);
        $this->client->request('GET', "/api/utilisateurs/{$user->getId()}", [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
    }

    public function testShowProfilAutreUtilisateur(): void
    {
        $admin = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'admin.util@test.com']);
        $this->client->request('GET', "/api/utilisateurs/{$admin->getId()}", [], [], $this->authHeader($this->userToken));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testShowInexistant(): void
    {
        $this->client->request('GET', '/api/utilisateurs/99999', [], [], $this->authHeader($this->adminToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateRefuseSiNonAdmin(): void
    {
        $this->client->request(
            'POST', '/api/utilisateurs',
            [], [],
            $this->jsonHeader($this->userToken),
            json_encode(['nom' => 'A', 'prenom' => 'B', 'email' => 'new@test.com', 'password' => 'pass'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateSuccesSiAdmin(): void
    {
        $this->client->request(
            'POST', '/api/utilisateurs',
            [], [],
            $this->jsonHeader($this->adminToken),
            json_encode([
                'nom'     => 'Nouveau',
                'prenom'  => 'User',
                'email'   => 'nouveau.' . uniqid() . '@test.com',
                'password' => 'password123',
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testUpdateSonPropreProfil(): void
    {
        $user = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.util@test.com']);
        $this->client->request(
            'PATCH', "/api/utilisateurs/{$user->getId()}",
            [], [],
            $this->jsonHeader($this->userToken),
            json_encode(['nom' => 'NouveauNom'])
        );
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('NouveauNom', $data['nom']);
    }

    public function testUpdateProfilAutreRefuse(): void
    {
        $admin = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'admin.util@test.com']);
        $this->client->request(
            'PATCH', "/api/utilisateurs/{$admin->getId()}",
            [], [],
            $this->jsonHeader($this->userToken),
            json_encode(['nom' => 'Hack'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteRefuseSiNonAdmin(): void
    {
        $user = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'user.util@test.com']);
        $this->client->request('DELETE', "/api/utilisateurs/{$user->getId()}", [], [], $this->authHeader($this->userToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends ApiTestCase
{
    public function testRegisterSucces(): void
    {
        $this->client->request(
            'POST', '/api/auth/register',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'nom'      => 'Dupont',
                'prenom'   => 'Jean',
                'email'    => 'jean.dupont.' . uniqid() . '@test.com',
                'password' => 'motdepasse123',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
    }

    public function testRegisterEmailDejaUtilise(): void
    {
        $email = 'doublon.' . uniqid() . '@test.com';

        $payload = json_encode([
            'nom' => 'A', 'prenom' => 'B', 'email' => $email, 'password' => 'pass',
        ]);

        $this->client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testRegisterEmailInvalide(): void
    {
        $this->client->request(
            'POST', '/api/auth/register',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['nom' => 'A', 'prenom' => 'B', 'email' => 'pas-un-email', 'password' => 'pass'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRegisterChampManquant(): void
    {
        $this->client->request(
            'POST', '/api/auth/register',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['nom' => 'A', 'email' => 'test@test.com'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testLoginSucces(): void
    {
        $email    = 'login.test.' . uniqid() . '@test.com';
        $password = 'password123';
        $this->createUtilisateur($email, $password);

        $this->client->request(
            'POST', '/api/auth/login',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testLoginMauvaisMotDePasse(): void
    {
        $email = 'bad.pass.' . uniqid() . '@test.com';
        $this->createUtilisateur($email, 'bonpassword');

        $this->client->request(
            'POST', '/api/auth/login',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => 'mauvaispassword'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoginUtilisateurInexistant(): void
    {
        $this->client->request(
            'POST', '/api/auth/login',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'inexistant@test.com', 'password' => 'pass'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}

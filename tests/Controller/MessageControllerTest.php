<?php

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Enum\RoleEnum;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class MessageControllerTest extends ApiTestCase
{
    private string $expediteurToken;
    private string $destinataireToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expediteurToken   = $this->getToken('exped.msg@test.com', 'password', RoleEnum::USER);
        $this->destinataireToken = $this->getToken('dest.msg@test.com', 'password', RoleEnum::USER);
    }

    private function createMessageFixture(): Message
    {
        $em          = $this->em();
        $expediteur  = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'exped.msg@test.com']);
        $destinataire = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'dest.msg@test.com']);

        $message = new Message();
        $message->setContenu('Bonjour, votre bateau est-il disponible ?');
        $message->setExpediteur($expediteur);
        $message->setDestinataire($destinataire);
        $em->persist($message);
        $em->flush();

        return $message;
    }

    public function testListEstAccessible(): void
    {
        $this->client->request('GET', '/api/messages', [], [], $this->authHeader($this->expediteurToken));
        $this->assertResponseIsSuccessful();
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testShowInexistant(): void
    {
        $this->client->request('GET', '/api/messages/99999', [], [], $this->authHeader($this->expediteurToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateChampManquant(): void
    {
        $this->client->request(
            'POST', '/api/messages', [], [],
            $this->jsonHeader($this->expediteurToken),
            json_encode(['contenu' => 'Test'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCreateSucces(): void
    {
        $em           = $this->em();
        $expediteur   = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'exped.msg@test.com']);
        $destinataire = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'dest.msg@test.com']);

        $this->client->request(
            'POST', '/api/messages', [], [],
            $this->jsonHeader($this->expediteurToken),
            json_encode([
                'contenu'         => 'Bonjour !',
                'id_utilisateur'  => $expediteur->getId(),
                'id_utilisateur_1' => $destinataire->getId(),
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateUtilisateurInexistant(): void
    {
        $expediteur = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'exped.msg@test.com']);

        $this->client->request(
            'POST', '/api/messages', [], [],
            $this->jsonHeader($this->expediteurToken),
            json_encode([
                'contenu'          => 'Test',
                'id_utilisateur'   => $expediteur->getId(),
                'id_utilisateur_1' => 99999,
            ])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testMarquerLu(): void
    {
        $message = $this->createMessageFixture();
        $this->assertFalse($message->isLu());

        $this->client->request(
            'PATCH', "/api/messages/{$message->getId()}/lu",
            [], [],
            $this->authHeader($this->destinataireToken)
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['lu']);
    }

    public function testDeleteSucces(): void
    {
        $message = $this->createMessageFixture();
        $this->client->request('DELETE', "/api/messages/{$message->getId()}", [], [], $this->authHeader($this->expediteurToken));
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}

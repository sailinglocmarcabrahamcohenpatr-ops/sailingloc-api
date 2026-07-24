<?php

namespace App\Tests;

use App\Entity\Bateau;
use App\Entity\Contrat;
use App\Entity\ModeDePaiement;
use App\Entity\Port;
use App\Entity\StatutPaiement;
use App\Entity\StatutReservation;
use App\Entity\TypeBateau;
use App\Entity\Utilisateur;
use App\Enum\RoleEnum;
use App\Enum\StatutBateauEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    // ------------------------------------------------------------------ auth

    protected function getToken(string $email, string $password, RoleEnum $role = RoleEnum::USER): string
    {
        $em     = $this->em();
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $repo   = $em->getRepository(Utilisateur::class);
        $user   = $repo->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new Utilisateur();
            $user->setNom('Test');
            $user->setPrenom('User');
            $user->setEmail($email);
            $user->setPassword($hasher->hashPassword($user, $password));
            $user->setStatutCompte('actif');
            if ($role !== RoleEnum::USER) {
                $user->addRole($role);
            }
            $em->persist($user);
            $em->flush();
        }

        $this->client->request(
            'POST', '/api/auth/login',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $data = json_decode($this->client->getResponse()->getContent(), true);

        return $data['token'] ?? '';
    }

    protected function authHeader(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $token];
    }

    protected function jsonHeader(string $token): array
    {
        return array_merge(
            $this->authHeader($token),
            ['CONTENT_TYPE' => 'application/json']
        );
    }

    // ------------------------------------------------------------------ em

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    // ------------------------------------------------------------------ fixtures

    protected function createUtilisateur(string $email, string $password = 'password', RoleEnum $role = RoleEnum::USER): Utilisateur
    {
        $em     = $this->em();
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user   = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new Utilisateur();
            $user->setNom('Test');
            $user->setPrenom('User');
            $user->setEmail($email);
            $user->setPassword($hasher->hashPassword($user, $password));
            $user->setStatutCompte('actif');
            if ($role !== RoleEnum::USER) {
                $user->addRole($role);
            }
            $em->persist($user);
            $em->flush();
        }

        return $user;
    }

    protected function createPort(string $nom = 'Port Test'): Port
    {
        $em   = $this->em();
        $port = $em->getRepository(Port::class)->findOneBy(['nom' => $nom]);

        if (!$port) {
            $port = new Port();
            $port->setNom($nom);
            $port->setPays('France');
            $port->setVille('Marseille');
            $em->persist($port);
            $em->flush();
        }

        return $port;
    }

    protected function createTypeBateau(string $label = 'Voilier Test'): TypeBateau
    {
        $em   = $this->em();
        $type = $em->getRepository(TypeBateau::class)->findOneBy(['labelTypeBateau' => $label]);

        if (!$type) {
            $type = new TypeBateau();
            $type->setLabelTypeBateau($label);
            $em->persist($type);
            $em->flush();
        }

        return $type;
    }

    protected function createBateau(Utilisateur $proprietaire, ?Port $port = null, ?TypeBateau $type = null): Bateau
    {
        $em    = $this->em();
        $port  = $port  ?? $this->createPort();
        $type  = $type  ?? $this->createTypeBateau();

        $bateau = new Bateau();
        $bateau->setNomBateau('Bateau Test ' . uniqid());
        $bateau->setMotorisation('Voile');
        $bateau->setTaille('10m');
        $bateau->setAvecSkipper(false);
        $bateau->setStatut(StatutBateauEnum::DISPONIBLE);
        $bateau->setPrixJour('150.00');
        $bateau->setPort($port);
        $bateau->setProprietaire($proprietaire);
        $bateau->setTypeBateau($type);
        $em->persist($bateau);
        $em->flush();

        return $bateau;
    }

    protected function createStatutReservation(string $label = 'En attente'): StatutReservation
    {
        $em     = $this->em();
        $statut = $em->getRepository(StatutReservation::class)->findOneBy(['labelStatutReservation' => $label]);

        if (!$statut) {
            $statut = new StatutReservation();
            $statut->setLabelStatutReservation($label);
            $em->persist($statut);
            $em->flush();
        }

        return $statut;
    }

    protected function createContrat(): Contrat
    {
        $em     = $this->em();
        $contrat = new Contrat();
        $contrat->setConditions('Conditions générales de test.');
        $contrat->setAssuranceIncluse(false);
        $contrat->setStatutContrat('actif');
        $em->persist($contrat);
        $em->flush();

        return $contrat;
    }

    protected function createStatutPaiement(string $label = 'En attente'): StatutPaiement
    {
        $em     = $this->em();
        $statut = $em->getRepository(StatutPaiement::class)->findOneBy(['labelStatutPaiement' => $label]);

        if (!$statut) {
            $statut = new StatutPaiement();
            $statut->setLabelStatutPaiement($label);
            $em->persist($statut);
            $em->flush();
        }

        return $statut;
    }

    protected function createModePaiement(string $label = 'Carte bancaire'): ModeDePaiement
    {
        $em   = $this->em();
        $mode = $em->getRepository(ModeDePaiement::class)->findOneBy(['labelModePaiement' => $label]);

        if (!$mode) {
            $mode = new ModeDePaiement();
            $mode->setLabelModePaiement($label);
            $em->persist($mode);
            $em->flush();
        }

        return $mode;
    }
}

<?php

namespace App\Tests\Controller;

use App\Entity\Paiement;
use App\Entity\Reservation;
use App\Enum\RoleEnum;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class DashboardControllerTest extends ApiTestCase
{
    public function testProprietaireRefuseSiSimpleUtilisateur(): void
    {
        $token = $this->getToken('user.dashboard@test.com', 'password', RoleEnum::USER);
        $this->client->request('GET', '/api/dashboard/proprietaire', [], [], $this->authHeader($token));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminRefuseSiProprietaire(): void
    {
        $token = $this->getToken('proprio.dashboard1@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $this->client->request('GET', '/api/dashboard/admin', [], [], $this->authHeader($token));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminSucces(): void
    {
        $token = $this->getToken('admin.dashboard@test.com', 'password', RoleEnum::ADMIN);
        $this->client->request('GET', '/api/dashboard/admin', [], [], $this->authHeader($token));
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        foreach (['nombre_utilisateurs', 'nombre_bateaux', 'nombre_bateaux_en_attente_validation', 'nombre_reservations', 'nombre_demandes_proprietaire_en_attente', 'chiffre_affaires_total', 'chiffre_affaires_mois_courant'] as $cle) {
            $this->assertArrayHasKey($cle, $data);
        }
    }

    public function testProprietaireSansBateauRenvoieDesZeros(): void
    {
        $token = $this->getToken('proprio.dashboard2@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $this->client->request('GET', '/api/dashboard/proprietaire', [], [], $this->authHeader($token));
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(0, $data['nombre_bateaux']);
        $this->assertSame(0, $data['reservations_a_venir']);
        $this->assertEquals(0.0, (float) $data['chiffre_affaires_total']);
        $this->assertEquals(0.0, (float) $data['taux_occupation_mois_courant']);
    }

    public function testProprietaireCompteSesBateauxEtSesReservationsAVenir(): void
    {
        $em = $this->em();
        $proprietaireToken = $this->getToken('proprio.dashboard3@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $proprietaire = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'proprio.dashboard3@test.com']);
        $locataire    = $this->createUtilisateur('locataire.dashboard3@test.com');
        $bateau       = $this->createBateau($proprietaire);
        $contrat      = $this->createContrat();
        $statut       = $this->createStatutReservation();

        // Réservation à venir (dans le futur)
        $resaFuture = new Reservation();
        $resaFuture->setDateDebut(new \DateTime('+10 days'));
        $resaFuture->setDateFin(new \DateTime('+15 days'));
        $resaFuture->setMontantTotal('900.00');
        $resaFuture->setBateau($bateau);
        $resaFuture->setUtilisateur($locataire);
        $resaFuture->setContrat($contrat);
        $resaFuture->setStatutReservation($statut);
        $em->persist($resaFuture);

        // Réservation passée : ne doit pas compter dans "à venir"
        $resaPassee = new Reservation();
        $resaPassee->setDateDebut(new \DateTime('-20 days'));
        $resaPassee->setDateFin(new \DateTime('-15 days'));
        $resaPassee->setMontantTotal('600.00');
        $resaPassee->setBateau($bateau);
        $resaPassee->setUtilisateur($locataire);
        $resaPassee->setContrat($this->createContrat());
        $resaPassee->setStatutReservation($statut);
        $em->persist($resaPassee);
        $em->flush();

        $this->client->request('GET', '/api/dashboard/proprietaire', [], [], $this->authHeader($proprietaireToken));
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['nombre_bateaux']);
        $this->assertSame(1, $data['reservations_a_venir']);
    }

    public function testProprietaireSommeLesPaiementsDeSesBateaux(): void
    {
        $em = $this->em();
        $proprietaireToken = $this->getToken('proprio.dashboard4@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $proprietaire = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'proprio.dashboard4@test.com']);
        $locataire    = $this->createUtilisateur('locataire.dashboard4@test.com');
        $bateau       = $this->createBateau($proprietaire);
        $contrat      = $this->createContrat();
        $statut       = $this->createStatutReservation();
        $statutPaiement = $this->createStatutPaiement();
        $modePaiement   = $this->createModePaiement();

        $resa = new Reservation();
        $resa->setDateDebut(new \DateTime('+5 days'));
        $resa->setDateFin(new \DateTime('+8 days'));
        $resa->setMontantTotal('500.00');
        $resa->setBateau($bateau);
        $resa->setUtilisateur($locataire);
        $resa->setContrat($contrat);
        $resa->setStatutReservation($statut);
        $em->persist($resa);
        $em->flush();

        $paiement1 = new Paiement();
        $paiement1->setMontant('300.00');
        $paiement1->setReservation($resa);
        $paiement1->setStatutPaiementRef($statutPaiement);
        $paiement1->setModePaiement($modePaiement);
        $em->persist($paiement1);

        $paiement2 = new Paiement();
        $paiement2->setMontant('200.00');
        $paiement2->setReservation($resa);
        $paiement2->setStatutPaiementRef($statutPaiement);
        $paiement2->setModePaiement($modePaiement);
        $em->persist($paiement2);
        $em->flush();

        $this->client->request('GET', '/api/dashboard/proprietaire', [], [], $this->authHeader($proprietaireToken));
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(500.0, (float) $data['chiffre_affaires_total']);
    }

    public function testProprietaireNeVoitPasLesPaiementsDunAutreProprietaire(): void
    {
        $em = $this->em();
        $proprietaireAToken = $this->getToken('proprio.dashboard5a@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $proprietaireA = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => 'proprio.dashboard5a@test.com']);
        $proprietaireB = $this->createUtilisateur('proprio.dashboard5b@test.com', 'password', RoleEnum::PROPRIETAIRE);
        $locataire     = $this->createUtilisateur('locataire.dashboard5@test.com');

        $bateauA = $this->createBateau($proprietaireA);
        $bateauB = $this->createBateau($proprietaireB);
        $contrat = $this->createContrat();
        $statut  = $this->createStatutReservation();
        $statutPaiement = $this->createStatutPaiement();
        $modePaiement   = $this->createModePaiement();

        $resaB = new Reservation();
        $resaB->setDateDebut(new \DateTime('+5 days'));
        $resaB->setDateFin(new \DateTime('+8 days'));
        $resaB->setMontantTotal('1000.00');
        $resaB->setBateau($bateauB);
        $resaB->setUtilisateur($locataire);
        $resaB->setContrat($contrat);
        $resaB->setStatutReservation($statut);
        $em->persist($resaB);
        $em->flush();

        $paiementB = new Paiement();
        $paiementB->setMontant('1000.00');
        $paiementB->setReservation($resaB);
        $paiementB->setStatutPaiementRef($statutPaiement);
        $paiementB->setModePaiement($modePaiement);
        $em->persist($paiementB);
        $em->flush();

        $this->client->request('GET', '/api/dashboard/proprietaire', [], [], $this->authHeader($proprietaireAToken));
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['nombre_bateaux']); // uniquement bateauA
        $this->assertEquals(0.0, (float) $data['chiffre_affaires_total']); // le paiement de B n'est pas compté
    }
}

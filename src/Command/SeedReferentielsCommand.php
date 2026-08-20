<?php

namespace App\Command;

use App\Entity\Assurance;
use App\Entity\ModeDePaiement;
use App\Entity\Port;
use App\Entity\StatutPaiement;
use App\Entity\StatutReservation;
use App\Entity\TypeBateau;
use App\Entity\TypeDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-referentiels',
    description: 'Insère les données de référence (types, statuts, modes) si elles n\'existent pas encore',
)]
class SeedReferentielsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seed des référentiels');

        $this->seedTypesBateaux($io);
        $this->seedTypesDocuments($io);
        $this->seedStatutsReservation($io);
        $this->seedStatutsPaiement($io);
        $this->seedModesPaiement($io);
        $this->seedAssurances($io);
        $this->seedPorts($io);

        $this->em->flush();

        $io->success('Référentiels à jour.');

        return Command::SUCCESS;
    }

    private function seedTypesBateaux(SymfonyStyle $io): void
    {
        $repo = $this->em->getRepository(TypeBateau::class);
        $labels = [
            'Voilier', 'Catamaran', 'Yacht à moteur', 'Bateau de pêche',
            'Bateau pneumatique', 'Péniche', 'Bateau de croisière', 'Kayak / Canoë',
        ];

        $inserted = 0;
        foreach ($labels as $label) {
            if ($repo->findOneBy(['labelTypeBateau' => $label])) {
                continue;
            }
            $entity = new TypeBateau();
            $entity->setLabelTypeBateau($label);
            $this->em->persist($entity);
            $inserted++;
        }

        $io->writeln(sprintf('  TypeBateau    : %d insérés', $inserted));
    }

    private function seedTypesDocuments(SymfonyStyle $io): void
    {
        $repo = $this->em->getRepository(TypeDocument::class);
        $labels = [
            "Carte d'identité", 'Passeport', 'Permis bateau côtier',
            'Permis bateau hauturier', 'Permis fluvial',
            'Certificat médical', 'Attestation de formation',
            'Carte grise', 'Assurance', 'Certificat CE',
        ];

        $inserted = 0;
        foreach ($labels as $label) {
            if ($repo->findOneBy(['labelTypeDocument' => $label])) {
                continue;
            }
            $entity = new TypeDocument();
            $entity->setLabelTypeDocument($label);
            $this->em->persist($entity);
            $inserted++;
        }

        $io->writeln(sprintf('  TypeDocument  : %d insérés', $inserted));
    }

    private function seedStatutsReservation(SymfonyStyle $io): void
    {
        $repo = $this->em->getRepository(StatutReservation::class);
        $labels = ['En attente', 'Confirmée', 'Annulée', 'Terminée', 'Refusée'];

        $inserted = 0;
        foreach ($labels as $label) {
            if ($repo->findOneBy(['labelStatutReservation' => $label])) {
                continue;
            }
            $entity = new StatutReservation();
            $entity->setLabelStatutReservation($label);
            $this->em->persist($entity);
            $inserted++;
        }

        $io->writeln(sprintf('  StatutResa    : %d insérés', $inserted));
    }

    private function seedStatutsPaiement(SymfonyStyle $io): void
    {
        $repo = $this->em->getRepository(StatutPaiement::class);
        $labels = ['en_attente', 'payé', 'remboursé', 'échoué', 'annulé'];

        $inserted = 0;
        foreach ($labels as $label) {
            if ($repo->findOneBy(['labelStatutPaiement' => $label])) {
                continue;
            }
            $entity = new StatutPaiement();
            $entity->setLabelStatutPaiement($label);
            $this->em->persist($entity);
            $inserted++;
        }

        $io->writeln(sprintf('  StatutPaie    : %d insérés', $inserted));
    }

    private function seedModesPaiement(SymfonyStyle $io): void
    {
        $repo = $this->em->getRepository(ModeDePaiement::class);
        $labels = ['Carte bancaire', 'Virement bancaire', 'PayPal', 'Prélèvement automatique', 'Chèque'];

        $inserted = 0;
        foreach ($labels as $label) {
            if ($repo->findOneBy(['labelModePaiement' => $label])) {
                continue;
            }
            $entity = new ModeDePaiement();
            $entity->setLabelModePaiement($label);
            $this->em->persist($entity);
            $inserted++;
        }

        $io->writeln(sprintf('  ModePaiement  : %d insérés', $inserted));
    }

    private function seedAssurances(SymfonyStyle $io): void
    {
        $repo = $this->em->getRepository(Assurance::class);
        $data = [
            ['nom' => 'Assurance basique',       'description' => 'Couverture responsabilité civile uniquement',      'prix' => '50.00',  'type' => 'basique'],
            ['nom' => 'Assurance tous risques',   'description' => 'Couverture complète dommages et responsabilité civile', 'prix' => '120.00', 'type' => 'tous_risques'],
            ['nom' => 'Assurance annulation',     'description' => "Remboursement en cas d'annulation imprévue",       'prix' => '35.00',  'type' => 'annulation'],
            ['nom' => 'Assurance rapatriement',   'description' => 'Prise en charge du rapatriement en mer',           'prix' => '80.00',  'type' => 'rapatriement'],
            ['nom' => 'Pack aventure',            'description' => 'Assurance complète pour navigation hauturière',    'prix' => '200.00', 'type' => 'premium'],
        ];

        $inserted = 0;
        foreach ($data as $d) {
            if ($repo->findOneBy(['nom' => $d['nom']])) {
                continue;
            }
            $entity = new Assurance();
            $entity->setNom($d['nom']);
            $entity->setDescription($d['description']);
            $entity->setPrix($d['prix']);
            $entity->setType($d['type']);
            $this->em->persist($entity);
            $inserted++;
        }

        $io->writeln(sprintf('  Assurance     : %d insérées', $inserted));
    }

    private function seedPorts(SymfonyStyle $io): void
    {
        $repo = $this->em->getRepository(Port::class);
        $ports = [
            ['nom' => 'Port Vieux',              'ville' => 'Marseille',       'pays' => 'France', 'codePostal' => '13002', 'latitude' => '43.295279',  'longitude' => '5.374817'],
            ['nom' => 'Port de Nice',             'ville' => 'Nice',            'pays' => 'France', 'codePostal' => '06300', 'latitude' => '43.694832',  'longitude' => '7.279390'],
            ['nom' => 'Port de La Rochelle',      'ville' => 'La Rochelle',     'pays' => 'France', 'codePostal' => '17000', 'latitude' => '46.156974',  'longitude' => '-1.151259'],
            ['nom' => 'Port du Crouesty',         'ville' => 'Arzon',           'pays' => 'France', 'codePostal' => '56640', 'latitude' => '47.540639',  'longitude' => '-2.899472'],
            ['nom' => 'Port de Saint-Tropez',     'ville' => 'Saint-Tropez',    'pays' => 'France', 'codePostal' => '83990', 'latitude' => '43.272024',  'longitude' => '6.639611'],
            ['nom' => 'Port de Cannes',           'ville' => 'Cannes',          'pays' => 'France', 'codePostal' => '06400', 'latitude' => '43.549139',  'longitude' => '7.018603'],
            ['nom' => 'Port de Brest',            'ville' => 'Brest',           'pays' => 'France', 'codePostal' => '29200', 'latitude' => '48.381855',  'longitude' => '-4.494380'],
            ['nom' => 'Port de Toulon',           'ville' => 'Toulon',          'pays' => 'France', 'codePostal' => '83000', 'latitude' => '43.124228',  'longitude' => '5.928000'],
            ['nom' => 'Port de Sète',             'ville' => 'Sète',            'pays' => 'France', 'codePostal' => '34200', 'latitude' => '43.404369',  'longitude' => '3.685700'],
            ['nom' => 'Port de Lorient',          'ville' => 'Lorient',         'pays' => 'France', 'codePostal' => '56100', 'latitude' => '47.749000',  'longitude' => '-3.358000'],
        ];

        $inserted = 0;
        foreach ($ports as $p) {
            if ($repo->findOneBy(['nom' => $p['nom'], 'ville' => $p['ville']])) {
                continue;
            }
            $entity = new Port();
            $entity->setNom($p['nom']);
            $entity->setVille($p['ville']);
            $entity->setPays($p['pays']);
            $entity->setCodePostal($p['codePostal']);
            $entity->setLatitude($p['latitude']);
            $entity->setLongitude($p['longitude']);
            $this->em->persist($entity);
            $inserted++;
        }

        $io->writeln(sprintf('  Port          : %d insérés', $inserted));
    }
}

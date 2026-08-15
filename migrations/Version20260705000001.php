<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Données de référence : types bateaux, types documents, statuts réservation, statuts paiement, modes de paiement, assurances';
    }

    public function up(Schema $schema): void
    {
        // Types de bateaux
        $this->addSql("INSERT INTO type_bateau (label_type_bateau) VALUES
            ('Voilier'),
            ('Catamaran'),
            ('Yacht à moteur'),
            ('Bateau de pêche'),
            ('Bateau pneumatique'),
            ('Péniche'),
            ('Bateau de croisière'),
            ('Kayak / Canoë')
        ");

        // Types de documents
        $this->addSql("INSERT INTO type_document (label_type_document) VALUES
            ('Carte d''identité'),
            ('Passeport'),
            ('Permis bateau côtier'),
            ('Permis bateau hauturier'),
            ('Permis fluvial'),
            ('Assurance personnelle'),
            ('Certificat médical'),
            ('Carte grise du bateau'),
            ('Attestation de formation')
        ");

        // Statuts de réservation
        $this->addSql("INSERT INTO statut_reservation (label_statut_reservation) VALUES
            ('En attente'),
            ('Confirmée'),
            ('Annulée'),
            ('Terminée'),
            ('Refusée')
        ");

        // Statuts de paiement
        $this->addSql("INSERT INTO statut_paiement (label_statut_paiement) VALUES
            ('en_attente'),
            ('payé'),
            ('remboursé'),
            ('échoué'),
            ('annulé')
        ");

        // Modes de paiement
        $this->addSql("INSERT INTO mode_de_paiement (label_mode_paiement) VALUES
            ('Carte bancaire'),
            ('Virement bancaire'),
            ('PayPal'),
            ('Prélèvement automatique'),
            ('Chèque')
        ");

        // Assurances
        $this->addSql("INSERT INTO assurance (nom, description, prix, type) VALUES
            ('Assurance basique', 'Couverture responsabilité civile uniquement', '50.00', 'basique'),
            ('Assurance tous risques', 'Couverture complète dommages et responsabilité civile', '120.00', 'tous_risques'),
            ('Assurance annulation', 'Remboursement en cas d''annulation imprévue', '35.00', 'annulation'),
            ('Assurance rapatriement', 'Prise en charge du rapatriement en mer', '80.00', 'rapatriement'),
            ('Pack aventure', 'Assurance complète pour navigation hauturière', '200.00', 'premium')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM assurance WHERE type IN (\'basique\', \'tous_risques\', \'annulation\', \'rapatriement\', \'premium\')');
        $this->addSql('DELETE FROM mode_de_paiement WHERE label_mode_paiement IN (\'Carte bancaire\', \'Virement bancaire\', \'PayPal\', \'Prélèvement automatique\', \'Chèque\')');
        $this->addSql('DELETE FROM statut_paiement WHERE label_statut_paiement IN (\'en_attente\', \'payé\', \'remboursé\', \'échoué\', \'annulé\')');
        $this->addSql('DELETE FROM statut_reservation WHERE label_statut_reservation IN (\'En attente\', \'Confirmée\', \'Annulée\', \'Terminée\', \'Refusée\')');
        $this->addSql('DELETE FROM type_document WHERE label_type_document IN (\'Carte d\'\'identité\', \'Passeport\', \'Permis bateau côtier\', \'Permis bateau hauturier\', \'Permis fluvial\', \'Assurance personnelle\', \'Certificat médical\', \'Carte grise du bateau\', \'Attestation de formation\')');
        $this->addSql('DELETE FROM type_bateau WHERE label_type_bateau IN (\'Voilier\', \'Catamaran\', \'Yacht à moteur\', \'Bateau de pêche\', \'Bateau pneumatique\', \'Péniche\', \'Bateau de croisière\', \'Kayak / Canoë\')');
    }
}

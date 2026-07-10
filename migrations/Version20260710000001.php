<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Revue modélisation : suppression colonne dupliquée paiement, nullable contrat, UNIQUE avis, coordonnées port, renommage FK message, Enums statuts, UNIQUE tables référentiel';
    }

    public function up(Schema $schema): void
    {
        // ─── 1. paiement : suppression de la colonne VARCHAR redondante ───────────
        $this->addSql('ALTER TABLE paiement DROP COLUMN IF EXISTS statut_paiement');

        // ─── 2. reservation : contrat_id devient nullable ─────────────────────────
        // Permet de créer une réservation sans contrat pré-existant
        $this->addSql('ALTER TABLE reservation ALTER COLUMN contrat_id DROP NOT NULL');

        // ─── 3. avis : contrainte UNIQUE (utilisateur + réservation) ──────────────
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT uq_avis_utilisateur_reservation UNIQUE (utilisateur_id, reservation_id)');

        // ─── 4. port : latitude/longitude TEXT → NUMERIC(10,7) ────────────────────
        $this->addSql('ALTER TABLE port ALTER COLUMN latitude TYPE NUMERIC(10,7) USING latitude::NUMERIC(10,7)');
        $this->addSql('ALTER TABLE port ALTER COLUMN longitude TYPE NUMERIC(10,7) USING longitude::NUMERIC(10,7)');

        // ─── 5. message : renommage des colonnes FK ───────────────────────────────
        $this->addSql('ALTER TABLE message RENAME COLUMN id_utilisateur TO expediteur_id');
        $this->addSql('ALTER TABLE message RENAME COLUMN id_utilisateur_1 TO destinataire_id');

        // ─── 6. utilisateur : statut_compte VARCHAR → ENUM via NOT NULL + default ─
        // La colonne était nullable, on la rend NOT NULL avec valeur par défaut
        $this->addSql("UPDATE utilisateur SET statut_compte = 'inactif' WHERE statut_compte IS NULL");
        $this->addSql("ALTER TABLE utilisateur ALTER COLUMN statut_compte SET DEFAULT 'inactif'");
        $this->addSql('ALTER TABLE utilisateur ALTER COLUMN statut_compte SET NOT NULL');

        // ─── 7. contrat : statut_contrat VARCHAR(50) → VARCHAR(20) + valeur enum ──
        // Valeurs acceptées : en_attente, signe, annule, expire
        $this->addSql("UPDATE contrat SET statut_contrat = 'en_attente' WHERE statut_contrat NOT IN ('en_attente','signe','annule','expire')");
        $this->addSql('ALTER TABLE contrat ALTER COLUMN statut_contrat TYPE VARCHAR(20)');
        $this->addSql("ALTER TABLE contrat ALTER COLUMN statut_contrat SET DEFAULT 'en_attente'");

        // ─── 8. disponibilite : statut VARCHAR(50) → VARCHAR(20) + valeur enum ────
        // Valeurs acceptées : disponible, indisponible, bloque
        $this->addSql("UPDATE disponibilite SET statut = 'disponible' WHERE statut NOT IN ('disponible','indisponible','bloque')");
        $this->addSql('ALTER TABLE disponibilite ALTER COLUMN statut TYPE VARCHAR(20)');
        $this->addSql("ALTER TABLE disponibilite ALTER COLUMN statut SET DEFAULT 'disponible'");

        // ─── 9. Contraintes UNIQUE sur les tables de référentiel ─────────────────
        $this->addSql('ALTER TABLE statut_reservation ADD CONSTRAINT uq_label_statut_reservation UNIQUE (label_statut_reservation)');
        $this->addSql('ALTER TABLE type_bateau ADD CONSTRAINT uq_label_type_bateau UNIQUE (label_type_bateau)');
        $this->addSql('ALTER TABLE type_document ADD CONSTRAINT uq_label_type_document UNIQUE (label_type_document)');
        $this->addSql('ALTER TABLE mode_de_paiement ADD CONSTRAINT uq_label_mode_paiement UNIQUE (label_mode_paiement)');

        // ─── 10. Index supplémentaires pour les requêtes métier ──────────────────
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_disponibilite_bateau_dates ON disponibilite (bateau_id, date_debut, date_fin)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reservation_dates ON reservation (date_debut, date_fin)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reservation_statut ON reservation (statut_reservation_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_message_destinataire_lu ON message (destinataire_id, lu)');
    }

    public function down(Schema $schema): void
    {
        // ─── Indexes métier ───────────────────────────────────────────────────────
        $this->addSql('DROP INDEX IF EXISTS idx_message_destinataire_lu');
        $this->addSql('DROP INDEX IF EXISTS idx_reservation_statut');
        $this->addSql('DROP INDEX IF EXISTS idx_reservation_dates');
        $this->addSql('DROP INDEX IF EXISTS idx_disponibilite_bateau_dates');

        // ─── UNIQUE référentiel ───────────────────────────────────────────────────
        $this->addSql('ALTER TABLE mode_de_paiement DROP CONSTRAINT IF EXISTS uq_label_mode_paiement');
        $this->addSql('ALTER TABLE type_document DROP CONSTRAINT IF EXISTS uq_label_type_document');
        $this->addSql('ALTER TABLE type_bateau DROP CONSTRAINT IF EXISTS uq_label_type_bateau');
        $this->addSql('ALTER TABLE statut_reservation DROP CONSTRAINT IF EXISTS uq_label_statut_reservation');

        // ─── Restore disponibilite ────────────────────────────────────────────────
        $this->addSql('ALTER TABLE disponibilite ALTER COLUMN statut TYPE VARCHAR(50)');

        // ─── Restore contrat ──────────────────────────────────────────────────────
        $this->addSql('ALTER TABLE contrat ALTER COLUMN statut_contrat TYPE VARCHAR(50)');

        // ─── Restore utilisateur.statut_compte nullable ───────────────────────────
        $this->addSql('ALTER TABLE utilisateur ALTER COLUMN statut_compte DROP NOT NULL');
        $this->addSql('ALTER TABLE utilisateur ALTER COLUMN statut_compte DROP DEFAULT');

        // ─── Restore message FK column names ─────────────────────────────────────
        $this->addSql('ALTER TABLE message RENAME COLUMN expediteur_id TO id_utilisateur');
        $this->addSql('ALTER TABLE message RENAME COLUMN destinataire_id TO id_utilisateur_1');

        // ─── Restore port types ───────────────────────────────────────────────────
        $this->addSql('ALTER TABLE port ALTER COLUMN latitude TYPE TEXT USING latitude::TEXT');
        $this->addSql('ALTER TABLE port ALTER COLUMN longitude TYPE TEXT USING longitude::TEXT');

        // ─── Restore avis UNIQUE ──────────────────────────────────────────────────
        $this->addSql('ALTER TABLE avis DROP CONSTRAINT IF EXISTS uq_avis_utilisateur_reservation');

        // ─── Restore reservation.contrat_id NOT NULL ──────────────────────────────
        $this->addSql('ALTER TABLE reservation ALTER COLUMN contrat_id SET NOT NULL');

        // ─── Restore paiement.statut_paiement ────────────────────────────────────
        $this->addSql("ALTER TABLE paiement ADD COLUMN statut_paiement VARCHAR(50) NOT NULL DEFAULT ''");
    }
}

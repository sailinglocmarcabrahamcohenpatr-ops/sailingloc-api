<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260709000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table owner_request (demandes de statut propriétaire)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE owner_request (
                id                          BIGINT NOT NULL AUTO_INCREMENT,
                user_id                     BIGINT NOT NULL,
                owner_type                  VARCHAR(20) NOT NULL DEFAULT \'particulier\',
                phone                       VARCHAR(30) NOT NULL,
                address                     VARCHAR(255) NOT NULL,
                city                        VARCHAR(100) NOT NULL,
                postal_code                 VARCHAR(20) NOT NULL,
                country                     VARCHAR(100) NOT NULL DEFAULT \'France\',
                company_name                VARCHAR(255) DEFAULT NULL,
                siret                       VARCHAR(14) DEFAULT NULL,
                vat_number                  VARCHAR(30) DEFAULT NULL,
                identity_document_id        BIGINT DEFAULT NULL,
                proof_address_document_id   BIGINT DEFAULT NULL,
                status                      VARCHAR(20) NOT NULL DEFAULT \'pending\',
                admin_comment               LONGTEXT DEFAULT NULL,
                created_at                  DATETIME NOT NULL,
                validated_at                DATETIME DEFAULT NULL,
                validated_by                BIGINT DEFAULT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('ALTER TABLE owner_request ADD CONSTRAINT FK_OR_user FOREIGN KEY (user_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE owner_request ADD CONSTRAINT FK_OR_identity_doc FOREIGN KEY (identity_document_id) REFERENCES document (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE owner_request ADD CONSTRAINT FK_OR_proof_doc FOREIGN KEY (proof_address_document_id) REFERENCES document (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE owner_request ADD CONSTRAINT FK_OR_validated_by FOREIGN KEY (validated_by) REFERENCES utilisateur (id) ON DELETE SET NULL');

        $this->addSql('CREATE INDEX IDX_OR_user ON owner_request (user_id)');
        $this->addSql('CREATE INDEX IDX_OR_status ON owner_request (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE owner_request DROP FOREIGN KEY FK_OR_user');
        $this->addSql('ALTER TABLE owner_request DROP FOREIGN KEY FK_OR_identity_doc');
        $this->addSql('ALTER TABLE owner_request DROP FOREIGN KEY FK_OR_proof_doc');
        $this->addSql('ALTER TABLE owner_request DROP FOREIGN KEY FK_OR_validated_by');
        $this->addSql('DROP TABLE owner_request');
    }
}

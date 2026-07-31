<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table notification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE notification (
                id              BIGSERIAL PRIMARY KEY,
                type            VARCHAR(30) NOT NULL,
                titre           VARCHAR(255) NOT NULL,
                message         TEXT NOT NULL,
                lu              BOOLEAN NOT NULL,
                date_creation   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                destinataire_id BIGINT NOT NULL,
                reservation_id  BIGINT DEFAULT NULL
            )
        ');

        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_notification_destinataire FOREIGN KEY (destinataire_id) REFERENCES utilisateur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_notification_reservation FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE INDEX IDX_notification_destinataire ON notification (destinataire_id)');
        $this->addSql('CREATE INDEX IDX_notification_reservation ON notification (reservation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification');
    }
}

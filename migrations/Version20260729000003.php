<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'disponibilite : ajout du lien optionnel vers la reservation qui a créé le blocage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE disponibilite ADD COLUMN reservation_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_disponibilite_reservation FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_disponibilite_reservation ON disponibilite (reservation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_disponibilite_reservation');
        $this->addSql('ALTER TABLE disponibilite DROP CONSTRAINT FK_disponibilite_reservation');
        $this->addSql('ALTER TABLE disponibilite DROP COLUMN reservation_id');
    }
}

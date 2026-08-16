<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260707032136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bateau ADD caution NUMERIC(15, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE bateau ADD carburant_inclus BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE bateau ADD permis_requis BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE bateau ADD nombre_cabines INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bateau DROP caution');
        $this->addSql('ALTER TABLE bateau DROP carburant_inclus');
        $this->addSql('ALTER TABLE bateau DROP permis_requis');
        $this->addSql('ALTER TABLE bateau DROP nombre_cabines');
    }
}

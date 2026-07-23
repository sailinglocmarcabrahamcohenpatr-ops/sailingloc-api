<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'avis : ajout des sous-notes propriétaire / bateau / lieu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE avis ADD COLUMN note_proprietaire INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE avis ADD COLUMN note_bateau INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE avis ADD COLUMN note_lieu INT DEFAULT 0 NOT NULL');

        // Backfill : les avis existants reprennent leur note globale sur les 3 critères
        $this->addSql('UPDATE avis SET note_proprietaire = note, note_bateau = note, note_lieu = note');

        $this->addSql('ALTER TABLE avis ALTER COLUMN note_proprietaire DROP DEFAULT');
        $this->addSql('ALTER TABLE avis ALTER COLUMN note_bateau DROP DEFAULT');
        $this->addSql('ALTER TABLE avis ALTER COLUMN note_lieu DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE avis DROP COLUMN note_proprietaire');
        $this->addSql('ALTER TABLE avis DROP COLUMN note_bateau');
        $this->addSql('ALTER TABLE avis DROP COLUMN note_lieu');
    }
}

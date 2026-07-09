<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260709000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la colonne bateau_id sur la table document (lien Document -> Bateau)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD bateau_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76B1ECEC1 FOREIGN KEY (bateau_id) REFERENCES bateau (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D8698A76B1ECEC1 ON document (bateau_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76B1ECEC1');
        $this->addSql('DROP INDEX IDX_D8698A76B1ECEC1 ON document');
        $this->addSql('ALTER TABLE document DROP COLUMN bateau_id');
    }
}

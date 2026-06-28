<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ token_confirmation sur la table utilisateur et valeur par défaut inactif pour statut_compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD token_confirmation VARCHAR(64) DEFAULT NULL');
        $this->addSql("UPDATE utilisateur SET statut_compte = 'inactif' WHERE statut_compte IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur DROP COLUMN token_confirmation');
    }
}

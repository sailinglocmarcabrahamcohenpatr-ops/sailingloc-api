<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs token_reset_password et token_reset_password_expires_at sur la table utilisateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD token_reset_password VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD token_reset_password_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur DROP COLUMN token_reset_password');
        $this->addSql('ALTER TABLE utilisateur DROP COLUMN token_reset_password_expires_at');
    }
}

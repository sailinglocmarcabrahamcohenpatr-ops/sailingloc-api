<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'bateau : ajout de la date de création';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bateau ADD COLUMN date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE bateau ALTER COLUMN date_creation DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bateau DROP COLUMN date_creation');
    }
}

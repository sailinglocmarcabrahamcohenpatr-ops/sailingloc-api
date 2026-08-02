<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'bateau : cascade la suppression des photos et disponibilités (pas de valeur sans le bateau), garde reservation en RESTRICT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo_bateau DROP CONSTRAINT FK_F3DBC772A9706509');
        $this->addSql('ALTER TABLE photo_bateau ADD CONSTRAINT FK_F3DBC772A9706509 FOREIGN KEY (bateau_id) REFERENCES bateau (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('ALTER TABLE disponibilite DROP CONSTRAINT FK_2CBACE2FA9706509');
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_2CBACE2FA9706509 FOREIGN KEY (bateau_id) REFERENCES bateau (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photo_bateau DROP CONSTRAINT FK_F3DBC772A9706509');
        $this->addSql('ALTER TABLE photo_bateau ADD CONSTRAINT FK_F3DBC772A9706509 FOREIGN KEY (bateau_id) REFERENCES bateau (id) NOT DEFERRABLE');

        $this->addSql('ALTER TABLE disponibilite DROP CONSTRAINT FK_2CBACE2FA9706509');
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_2CBACE2FA9706509 FOREIGN KEY (bateau_id) REFERENCES bateau (id) NOT DEFERRABLE');
    }
}

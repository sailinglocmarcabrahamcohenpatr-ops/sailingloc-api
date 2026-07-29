<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'paiement : ajout stripe_payment_intent_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paiement ADD COLUMN stripe_payment_intent_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_paiement_stripe ON paiement (stripe_payment_intent_id)');
        $this->addSql('ALTER TABLE paiement ALTER COLUMN mode_paiement_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_paiement_stripe');
        $this->addSql('ALTER TABLE paiement DROP COLUMN stripe_payment_intent_id');
        $this->addSql('ALTER TABLE paiement ALTER COLUMN mode_paiement_id SET NOT NULL');
    }
}

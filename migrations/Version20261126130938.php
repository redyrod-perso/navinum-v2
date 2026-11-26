<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261126130938 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Webhooks sortants et journal des envois';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE webhook (id UUID NOT NULL, libelle VARCHAR(120) NOT NULL, url VARCHAR(2048) NOT NULL, evenements JSON NOT NULL, secret VARCHAR(80) NOT NULL, actif BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN webhook.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN webhook.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE webhook_envoi (id UUID NOT NULL, webhook_id UUID NOT NULL, evenement VARCHAR(64) NOT NULL, donnees JSON NOT NULL, statut VARCHAR(16) NOT NULL, tentatives INT DEFAULT 0 NOT NULL, code_http INT DEFAULT NULL, erreur VARCHAR(500) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_webhook_envoi_webhook ON webhook_envoi (webhook_id, created_at)');
        $this->addSql('CREATE INDEX IDX_40AED01F5C9BA60B ON webhook_envoi (webhook_id)');
        $this->addSql('COMMENT ON COLUMN webhook_envoi.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN webhook_envoi.webhook_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN webhook_envoi.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE webhook_envoi ADD CONSTRAINT FK_40AED01F5C9BA60B FOREIGN KEY (webhook_id) REFERENCES webhook (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webhook_envoi DROP CONSTRAINT FK_40AED01F5C9BA60B');
        $this->addSql('DROP TABLE webhook');
        $this->addSql('DROP TABLE webhook_envoi');
    }
}

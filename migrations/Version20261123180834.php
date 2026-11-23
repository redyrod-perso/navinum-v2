<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123180834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Appareils des visiteurs (sessions de 30 min, 12 h au plus, révocables)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE visiteur_appareil (id UUID NOT NULL, visiteur_id UUID NOT NULL, secret_hash VARCHAR(64) NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E1295CE9B390A88D ON visiteur_appareil (secret_hash)');
        $this->addSql('CREATE INDEX idx_visiteur_appareil_visiteur ON visiteur_appareil (visiteur_id)');
        $this->addSql('COMMENT ON COLUMN visiteur_appareil.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN visiteur_appareil.visiteur_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN visiteur_appareil.last_seen_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN visiteur_appareil.revoked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN visiteur_appareil.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE visiteur_appareil ADD CONSTRAINT FK_E1295CE97F72333D FOREIGN KEY (visiteur_id) REFERENCES visiteur (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE visiteur_appareil DROP CONSTRAINT FK_E1295CE97F72333D');
        $this->addSql('DROP TABLE visiteur_appareil');
    }
}

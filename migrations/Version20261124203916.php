<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124203916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clés d intégration (n8n…)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cle_integration (id UUID NOT NULL, libelle VARCHAR(120) NOT NULL, apercu VARCHAR(16) NOT NULL, secret_hash VARCHAR(64) NOT NULL, droits JSON NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E9E5423EB390A88D ON cle_integration (secret_hash)');
        $this->addSql('COMMENT ON COLUMN cle_integration.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN cle_integration.last_used_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN cle_integration.revoked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN cle_integration.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cle_integration');
    }
}

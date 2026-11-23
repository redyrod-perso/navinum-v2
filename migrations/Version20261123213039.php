<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123213039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identités OAuth des visiteurs (Google, Microsoft, Facebook…)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE visiteur_identite (id UUID NOT NULL, visiteur_id UUID NOT NULL, fournisseur VARCHAR(32) NOT NULL, sujet VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_visiteur_identite ON visiteur_identite (fournisseur, sujet)');
        $this->addSql('CREATE INDEX IDX_DAD789787F72333D ON visiteur_identite (visiteur_id)');
        $this->addSql('COMMENT ON COLUMN visiteur_identite.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN visiteur_identite.visiteur_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN visiteur_identite.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE visiteur_identite ADD CONSTRAINT FK_DAD789787F72333D FOREIGN KEY (visiteur_id) REFERENCES visiteur (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE visiteur_identite DROP CONSTRAINT FK_DAD789787F72333D');
        $this->addSql('DROP TABLE visiteur_identite');
    }
}

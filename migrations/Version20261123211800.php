<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123211800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comptes visiteurs : e-mail unique (minuscules) et date de vérification';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_visiteur_email');
        $this->addSql('ALTER TABLE visiteur ADD email_verifie_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN visiteur.email_verifie_at IS \'(DC2Type:datetime_immutable)\'');
        // Adresses normalisées (minuscules) avant l'unicité : les comptes se retrouvent sans tenir compte de la casse
        $this->addSql("UPDATE visiteur SET email = lower(trim(email)) WHERE email IS NOT NULL");
        $this->addSql('CREATE UNIQUE INDEX uniq_visiteur_email ON visiteur (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_visiteur_email');
        $this->addSql('ALTER TABLE visiteur DROP email_verifie_at');
        $this->addSql('CREATE INDEX idx_visiteur_email ON visiteur (email)');
    }
}

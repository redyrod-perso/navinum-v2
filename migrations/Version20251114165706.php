<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251114165706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE organisateur (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', libelle VARCHAR(255) NOT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_organisateur_libelle (libelle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE exposition ADD contexte_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD organisateur_editeur_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD organisateur_diffuseur_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD synopsis LONGTEXT DEFAULT NULL, ADD description LONGTEXT DEFAULT NULL, ADD logo VARCHAR(128) DEFAULT NULL, ADD publics VARCHAR(255) DEFAULT NULL, ADD langues VARCHAR(255) DEFAULT NULL, ADD url_illustration VARCHAR(255) DEFAULT NULL, ADD url_studio VARCHAR(255) DEFAULT NULL, ADD start_at DATE DEFAULT NULL, ADD end_at DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE exposition ADD CONSTRAINT FK_BC31FD1399B36A86 FOREIGN KEY (contexte_id) REFERENCES contexte (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE exposition ADD CONSTRAINT FK_BC31FD1316275ABF FOREIGN KEY (organisateur_editeur_id) REFERENCES organisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE exposition ADD CONSTRAINT FK_BC31FD1351399080 FOREIGN KEY (organisateur_diffuseur_id) REFERENCES organisateur (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_BC31FD1399B36A86 ON exposition (contexte_id)');
        $this->addSql('CREATE INDEX IDX_BC31FD1316275ABF ON exposition (organisateur_editeur_id)');
        $this->addSql('CREATE INDEX IDX_BC31FD1351399080 ON exposition (organisateur_diffuseur_id)');
        $this->addSql('ALTER TABLE exposition_parcours DROP FOREIGN KEY FK_254E99446E38C0DB');
        $this->addSql('ALTER TABLE exposition_parcours DROP FOREIGN KEY FK_254E994488ED476F');
        $this->addSql('DROP INDEX `primary` ON exposition_parcours');
        $this->addSql('ALTER TABLE exposition_parcours DROP id, DROP created_at, DROP updated_at, DROP is_tosync');
        $this->addSql('ALTER TABLE exposition_parcours ADD CONSTRAINT FK_254E99446E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id)');
        $this->addSql('ALTER TABLE exposition_parcours ADD CONSTRAINT FK_254E994488ED476F FOREIGN KEY (exposition_id) REFERENCES exposition (id)');
        $this->addSql('ALTER TABLE exposition_parcours ADD PRIMARY KEY (parcours_id, exposition_id)');
        $this->addSql('ALTER TABLE parcours_interactif DROP FOREIGN KEY FK_D0E46E276E38C0DB');
        $this->addSql('ALTER TABLE parcours_interactif DROP FOREIGN KEY FK_D0E46E27C5D2347F');
        $this->addSql('DROP INDEX `primary` ON parcours_interactif');
        $this->addSql('ALTER TABLE parcours_interactif DROP id, DROP created_at, DROP updated_at, DROP num_order, DROP is_tosync');
        $this->addSql('ALTER TABLE parcours_interactif ADD CONSTRAINT FK_D0E46E276E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id)');
        $this->addSql('ALTER TABLE parcours_interactif ADD CONSTRAINT FK_D0E46E27C5D2347F FOREIGN KEY (interactif_id) REFERENCES interactif (id)');
        $this->addSql('ALTER TABLE parcours_interactif ADD PRIMARY KEY (parcours_id, interactif_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exposition DROP FOREIGN KEY FK_BC31FD1316275ABF');
        $this->addSql('ALTER TABLE exposition DROP FOREIGN KEY FK_BC31FD1351399080');
        $this->addSql('DROP TABLE organisateur');
        $this->addSql('ALTER TABLE exposition DROP FOREIGN KEY FK_BC31FD1399B36A86');
        $this->addSql('DROP INDEX IDX_BC31FD1399B36A86 ON exposition');
        $this->addSql('DROP INDEX IDX_BC31FD1316275ABF ON exposition');
        $this->addSql('DROP INDEX IDX_BC31FD1351399080 ON exposition');
        $this->addSql('ALTER TABLE exposition DROP contexte_id, DROP organisateur_editeur_id, DROP organisateur_diffuseur_id, DROP synopsis, DROP description, DROP logo, DROP publics, DROP langues, DROP url_illustration, DROP url_studio, DROP start_at, DROP end_at');
        $this->addSql('ALTER TABLE parcours_interactif DROP FOREIGN KEY FK_D0E46E276E38C0DB');
        $this->addSql('ALTER TABLE parcours_interactif DROP FOREIGN KEY FK_D0E46E27C5D2347F');
        $this->addSql('DROP INDEX `PRIMARY` ON parcours_interactif');
        $this->addSql('ALTER TABLE parcours_interactif ADD id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME NOT NULL, ADD num_order INT DEFAULT NULL, ADD is_tosync TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE parcours_interactif ADD CONSTRAINT FK_D0E46E276E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parcours_interactif ADD CONSTRAINT FK_D0E46E27C5D2347F FOREIGN KEY (interactif_id) REFERENCES interactif (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parcours_interactif ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE exposition_parcours DROP FOREIGN KEY FK_254E99446E38C0DB');
        $this->addSql('ALTER TABLE exposition_parcours DROP FOREIGN KEY FK_254E994488ED476F');
        $this->addSql('DROP INDEX `PRIMARY` ON exposition_parcours');
        $this->addSql('ALTER TABLE exposition_parcours ADD id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME NOT NULL, ADD is_tosync TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE exposition_parcours ADD CONSTRAINT FK_254E99446E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exposition_parcours ADD CONSTRAINT FK_254E994488ED476F FOREIGN KEY (exposition_id) REFERENCES exposition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exposition_parcours ADD PRIMARY KEY (id)');
    }
}

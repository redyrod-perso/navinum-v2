<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251016160309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contexte (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, libelle VARCHAR(255) NOT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_contexte_libelle (libelle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE csp (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, libelle VARCHAR(255) NOT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_csp_libelle (libelle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE exposition (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, libelle VARCHAR(255) NOT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_exposition_libelle (libelle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE exposition_parcours (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', parcours_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', exposition_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_254E99446E38C0DB (parcours_id), INDEX IDX_254E994488ED476F (exposition_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE flotte (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', exposition_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, libelle VARCHAR(255) NOT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_759BD95488ED476F (exposition_id), UNIQUE INDEX uniq_flotte_libelle (libelle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE interactif (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, libelle VARCHAR(255) NOT NULL, source_type VARCHAR(32) DEFAULT NULL, synopsis LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, categorie VARCHAR(128) DEFAULT NULL, version VARCHAR(32) DEFAULT NULL, editeur VARCHAR(255) DEFAULT NULL, publics VARCHAR(255) DEFAULT NULL, markets VARCHAR(255) DEFAULT NULL, url_market_ios VARCHAR(255) DEFAULT NULL, url_market_android VARCHAR(255) DEFAULT NULL, url_market_windows VARCHAR(255) DEFAULT NULL, langues VARCHAR(255) DEFAULT NULL, image1 VARCHAR(255) DEFAULT NULL, image2 VARCHAR(255) DEFAULT NULL, image3 VARCHAR(255) DEFAULT NULL, date_diff DATE DEFAULT NULL, explications_resultats LONGTEXT DEFAULT NULL, score LONGTEXT DEFAULT NULL, variable LONGTEXT DEFAULT NULL, url_scheme VARCHAR(128) DEFAULT NULL, url_fichier_interactif VARCHAR(255) DEFAULT NULL, url_pierre_de_rosette VARCHAR(255) DEFAULT NULL, url_illustration VARCHAR(255) DEFAULT NULL, url_interactif_type INT DEFAULT NULL, url_interactif_choice VARCHAR(255) DEFAULT NULL, url_visiteur_type INT DEFAULT NULL, url_start_at INT DEFAULT NULL, url_start_at_type VARCHAR(255) DEFAULT NULL, url_end_at INT DEFAULT NULL, url_end_at_type VARCHAR(255) DEFAULT NULL, refresh_deploiement TINYINT(1) DEFAULT 0 NOT NULL, is_visiteur_needed TINYINT(1) DEFAULT 0 NOT NULL, is_logvisite_needed TINYINT(1) DEFAULT 0 NOT NULL, is_logvisite_verbose_needed TINYINT(1) DEFAULT 0 NOT NULL, is_parcours_needed TINYINT(1) DEFAULT 0 NOT NULL, ordre INT DEFAULT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_interactif_libelle (libelle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE langue (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, libelle VARCHAR(128) NOT NULL, short_libelle VARCHAR(10) NOT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_langue_libelle (libelle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE log_visite (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', interactif_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', visite_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', visiteur_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', exposition_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, interactif_libelle VARCHAR(255) DEFAULT NULL, connection VARCHAR(64) DEFAULT NULL, start_at DATETIME NOT NULL, end_at DATETIME DEFAULT NULL, resultats LONGTEXT DEFAULT NULL, score INT DEFAULT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_A566DF19C5D2347F (interactif_id), INDEX IDX_A566DF19C1C5DC59 (visite_id), INDEX IDX_A566DF197F72333D (visiteur_id), INDEX IDX_A566DF1988ED476F (exposition_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parcours (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, libelle VARCHAR(255) NOT NULL, ordre INT DEFAULT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_parcours_libelle (libelle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parcours_interactif (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', parcours_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', interactif_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, num_order INT DEFAULT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_D0E46E276E38C0DB (parcours_id), INDEX IDX_D0E46E27C5D2347F (interactif_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE peripherique (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', flotte_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', interactif_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, adresse_mac VARCHAR(64) NOT NULL, adresse_ip VARCHAR(32) DEFAULT NULL, serial_number VARCHAR(255) DEFAULT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_CFCF03653AD377B3 (flotte_id), INDEX IDX_CFCF0365C5D2347F (interactif_id), UNIQUE INDEX uniq_peripherique_adresse_mac (adresse_mac), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rfid (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', groupe_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, type VARCHAR(64) DEFAULT NULL, valeur1 VARCHAR(255) DEFAULT \'\' NOT NULL, valeur2 VARCHAR(255) DEFAULT \'\' NOT NULL, valeur3 VARCHAR(255) DEFAULT \'\' NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, is_resettable TINYINT(1) DEFAULT 1 NOT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_52C875367A45358C (groupe_id), INDEX idx_rfid_is_resettable (is_resettable), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rfid_groupe (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, nom VARCHAR(255) DEFAULT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_rfid_groupe_nom (nom), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rfid_groupe_visiteur (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', rfid_groupe_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', langue_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', csp_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, nom VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, genre VARCHAR(10) DEFAULT NULL, age INT DEFAULT NULL, code_postal INT DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_ADCBA71BF5094468 (rfid_groupe_id), INDEX IDX_ADCBA71B2AADBACD (langue_id), INDEX IDX_ADCBA71B73EFFAF6 (csp_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE visite (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', visiteur_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', navinum_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', groupe_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', exposition_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', parcours_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', interactif_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, exposition_libelle VARCHAR(255) DEFAULT NULL, parcours_libelle VARCHAR(255) DEFAULT NULL, connexion_id VARCHAR(255) DEFAULT NULL, is_ending TINYINT(1) DEFAULT 0 NOT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_B09C8CBB7F72333D (visiteur_id), INDEX IDX_B09C8CBB402D6A1A (navinum_id), INDEX IDX_B09C8CBB7A45358C (groupe_id), INDEX IDX_B09C8CBB88ED476F (exposition_id), INDEX IDX_B09C8CBB6E38C0DB (parcours_id), INDEX IDX_B09C8CBBC5D2347F (interactif_id), INDEX idx_visite_connexion (connexion_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE visiteur (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', contexte_creation_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', langue_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', csp_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, password_son VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, type VARCHAR(32) NOT NULL, has_photo TINYINT(1) DEFAULT 0 NOT NULL, genre VARCHAR(10) DEFAULT NULL, date_naissance DATE DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, nom VARCHAR(255) DEFAULT NULL, pseudo_son VARCHAR(255) DEFAULT NULL, has_newsletter TINYINT(1) DEFAULT NULL, url_avatar VARCHAR(255) DEFAULT NULL, num_mobile VARCHAR(64) DEFAULT NULL, facebook_id VARCHAR(255) DEFAULT NULL, google_id VARCHAR(255) DEFAULT NULL, twitter_id VARCHAR(255) DEFAULT NULL, flickr_id VARCHAR(255) DEFAULT NULL, dailymotion_id VARCHAR(255) DEFAULT NULL, is_anonyme TINYINT(1) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, is_tosync TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_4EA587B83574D86F (contexte_creation_id), INDEX IDX_4EA587B82AADBACD (langue_id), INDEX IDX_4EA587B873EFFAF6 (csp_id), INDEX idx_visiteur_email (email), INDEX idx_visiteur_password_son (password_son), INDEX idx_visiteur_pseudo_son (pseudo_son), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE exposition_parcours ADD CONSTRAINT FK_254E99446E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exposition_parcours ADD CONSTRAINT FK_254E994488ED476F FOREIGN KEY (exposition_id) REFERENCES exposition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE flotte ADD CONSTRAINT FK_759BD95488ED476F FOREIGN KEY (exposition_id) REFERENCES exposition (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE log_visite ADD CONSTRAINT FK_A566DF19C5D2347F FOREIGN KEY (interactif_id) REFERENCES interactif (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE log_visite ADD CONSTRAINT FK_A566DF19C1C5DC59 FOREIGN KEY (visite_id) REFERENCES visite (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE log_visite ADD CONSTRAINT FK_A566DF197F72333D FOREIGN KEY (visiteur_id) REFERENCES visiteur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE log_visite ADD CONSTRAINT FK_A566DF1988ED476F FOREIGN KEY (exposition_id) REFERENCES exposition (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE parcours_interactif ADD CONSTRAINT FK_D0E46E276E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parcours_interactif ADD CONSTRAINT FK_D0E46E27C5D2347F FOREIGN KEY (interactif_id) REFERENCES interactif (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE peripherique ADD CONSTRAINT FK_CFCF03653AD377B3 FOREIGN KEY (flotte_id) REFERENCES flotte (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE peripherique ADD CONSTRAINT FK_CFCF0365C5D2347F FOREIGN KEY (interactif_id) REFERENCES interactif (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rfid ADD CONSTRAINT FK_52C875367A45358C FOREIGN KEY (groupe_id) REFERENCES rfid_groupe (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rfid_groupe_visiteur ADD CONSTRAINT FK_ADCBA71BF5094468 FOREIGN KEY (rfid_groupe_id) REFERENCES rfid_groupe (id)');
        $this->addSql('ALTER TABLE rfid_groupe_visiteur ADD CONSTRAINT FK_ADCBA71B2AADBACD FOREIGN KEY (langue_id) REFERENCES langue (id)');
        $this->addSql('ALTER TABLE rfid_groupe_visiteur ADD CONSTRAINT FK_ADCBA71B73EFFAF6 FOREIGN KEY (csp_id) REFERENCES csp (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_B09C8CBB7F72333D FOREIGN KEY (visiteur_id) REFERENCES visiteur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_B09C8CBB402D6A1A FOREIGN KEY (navinum_id) REFERENCES rfid (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_B09C8CBB7A45358C FOREIGN KEY (groupe_id) REFERENCES rfid_groupe_visiteur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_B09C8CBB88ED476F FOREIGN KEY (exposition_id) REFERENCES exposition (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_B09C8CBB6E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_B09C8CBBC5D2347F FOREIGN KEY (interactif_id) REFERENCES interactif (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE visiteur ADD CONSTRAINT FK_4EA587B83574D86F FOREIGN KEY (contexte_creation_id) REFERENCES contexte (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE visiteur ADD CONSTRAINT FK_4EA587B82AADBACD FOREIGN KEY (langue_id) REFERENCES langue (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE visiteur ADD CONSTRAINT FK_4EA587B873EFFAF6 FOREIGN KEY (csp_id) REFERENCES csp (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exposition_parcours DROP FOREIGN KEY FK_254E99446E38C0DB');
        $this->addSql('ALTER TABLE exposition_parcours DROP FOREIGN KEY FK_254E994488ED476F');
        $this->addSql('ALTER TABLE flotte DROP FOREIGN KEY FK_759BD95488ED476F');
        $this->addSql('ALTER TABLE log_visite DROP FOREIGN KEY FK_A566DF19C5D2347F');
        $this->addSql('ALTER TABLE log_visite DROP FOREIGN KEY FK_A566DF19C1C5DC59');
        $this->addSql('ALTER TABLE log_visite DROP FOREIGN KEY FK_A566DF197F72333D');
        $this->addSql('ALTER TABLE log_visite DROP FOREIGN KEY FK_A566DF1988ED476F');
        $this->addSql('ALTER TABLE parcours_interactif DROP FOREIGN KEY FK_D0E46E276E38C0DB');
        $this->addSql('ALTER TABLE parcours_interactif DROP FOREIGN KEY FK_D0E46E27C5D2347F');
        $this->addSql('ALTER TABLE peripherique DROP FOREIGN KEY FK_CFCF03653AD377B3');
        $this->addSql('ALTER TABLE peripherique DROP FOREIGN KEY FK_CFCF0365C5D2347F');
        $this->addSql('ALTER TABLE rfid DROP FOREIGN KEY FK_52C875367A45358C');
        $this->addSql('ALTER TABLE rfid_groupe_visiteur DROP FOREIGN KEY FK_ADCBA71BF5094468');
        $this->addSql('ALTER TABLE rfid_groupe_visiteur DROP FOREIGN KEY FK_ADCBA71B2AADBACD');
        $this->addSql('ALTER TABLE rfid_groupe_visiteur DROP FOREIGN KEY FK_ADCBA71B73EFFAF6');
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_B09C8CBB7F72333D');
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_B09C8CBB402D6A1A');
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_B09C8CBB7A45358C');
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_B09C8CBB88ED476F');
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_B09C8CBB6E38C0DB');
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_B09C8CBBC5D2347F');
        $this->addSql('ALTER TABLE visiteur DROP FOREIGN KEY FK_4EA587B83574D86F');
        $this->addSql('ALTER TABLE visiteur DROP FOREIGN KEY FK_4EA587B82AADBACD');
        $this->addSql('ALTER TABLE visiteur DROP FOREIGN KEY FK_4EA587B873EFFAF6');
        $this->addSql('DROP TABLE contexte');
        $this->addSql('DROP TABLE csp');
        $this->addSql('DROP TABLE exposition');
        $this->addSql('DROP TABLE exposition_parcours');
        $this->addSql('DROP TABLE flotte');
        $this->addSql('DROP TABLE interactif');
        $this->addSql('DROP TABLE langue');
        $this->addSql('DROP TABLE log_visite');
        $this->addSql('DROP TABLE parcours');
        $this->addSql('DROP TABLE parcours_interactif');
        $this->addSql('DROP TABLE peripherique');
        $this->addSql('DROP TABLE rfid');
        $this->addSql('DROP TABLE rfid_groupe');
        $this->addSql('DROP TABLE rfid_groupe_visiteur');
        $this->addSql('DROP TABLE visite');
        $this->addSql('DROP TABLE visiteur');
        $this->addSql('DROP TABLE messenger_messages');
    }
}

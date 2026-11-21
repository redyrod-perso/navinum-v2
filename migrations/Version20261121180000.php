<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Interactifs configurables par parcours, comptes d'admin et jetons de lancement.
 *
 * - parcours_interactif : la table de jointure (parcours_id, interactif_id) devient une entité
 *   avec id, ordre et config JSON. Les liens existants sont conservés (ordre repris de interactif.ordre).
 * - admin_user : comptes de l'espace d'administration (remplace l'utilisateur en mémoire).
 * - launch_token : jetons ?nv= de lancement des interactifs.
 */
final class Version20261121180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ParcoursInteractif (ordre + config), admin_user, launch_token';
    }

    public function up(Schema $schema): void
    {
        // 1. parcours_interactif : jointure -> entité, en conservant les liens existants
        $this->addSql('ALTER TABLE parcours_interactif RENAME TO parcours_interactif_old');
        $this->addSql('ALTER TABLE parcours_interactif_old DROP CONSTRAINT FK_D0E46E276E38C0DB');
        $this->addSql('ALTER TABLE parcours_interactif_old DROP CONSTRAINT FK_D0E46E27C5D2347F');
        $this->addSql('DROP INDEX IF EXISTS IDX_D0E46E276E38C0DB');
        $this->addSql('DROP INDEX IF EXISTS IDX_D0E46E27C5D2347F');

        $this->addSql('CREATE TABLE parcours_interactif (id UUID NOT NULL, parcours_id UUID NOT NULL, interactif_id UUID NOT NULL, ordre INT DEFAULT 0 NOT NULL, config JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_parcours_interactif_parcours ON parcours_interactif (parcours_id)');
        $this->addSql('CREATE INDEX IDX_D0E46E27C5D2347F ON parcours_interactif (interactif_id)');
        $this->addSql("COMMENT ON COLUMN parcours_interactif.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN parcours_interactif.parcours_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN parcours_interactif.interactif_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN parcours_interactif.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE parcours_interactif ADD CONSTRAINT FK_D0E46E276E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE parcours_interactif ADD CONSTRAINT FK_D0E46E27C5D2347F FOREIGN KEY (interactif_id) REFERENCES interactif (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            INSERT INTO parcours_interactif (id, parcours_id, interactif_id, ordre, config, created_at, updated_at)
            SELECT gen_random_uuid(), o.parcours_id, o.interactif_id,
                   (ROW_NUMBER() OVER (PARTITION BY o.parcours_id ORDER BY i.ordre NULLS LAST, i.libelle)) - 1,
                   NULL, NOW(), NOW()
            FROM parcours_interactif_old o
            JOIN interactif i ON i.id = o.interactif_id
            SQL);
        $this->addSql('DROP TABLE parcours_interactif_old');

        // 2. Comptes d'administration
        $this->addSql('CREATE TABLE admin_user (id UUID NOT NULL, organisateur_id UUID DEFAULT NULL, email VARCHAR(180) NOT NULL, nom VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AD8A54A9E7927C74 ON admin_user (email)');
        $this->addSql('CREATE INDEX IDX_AD8A54A9D936B2FA ON admin_user (organisateur_id)');
        $this->addSql("COMMENT ON COLUMN admin_user.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN admin_user.organisateur_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN admin_user.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE admin_user ADD CONSTRAINT FK_AD8A54A9D936B2FA FOREIGN KEY (organisateur_id) REFERENCES organisateur (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // 3. Jetons de lancement
        $this->addSql('CREATE TABLE launch_token (id UUID NOT NULL, interactif_id UUID NOT NULL, parcours_interactif_id UUID DEFAULT NULL, exposition_id UUID DEFAULT NULL, visiteur_id UUID DEFAULT NULL, log_visite_id UUID DEFAULT NULL, token VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C1E6F32A5F37A13B ON launch_token (token)');
        $this->addSql('CREATE INDEX IDX_C1E6F32AC5D2347F ON launch_token (interactif_id)');
        $this->addSql('CREATE INDEX IDX_C1E6F32A75AF5CD ON launch_token (parcours_interactif_id)');
        $this->addSql('CREATE INDEX IDX_C1E6F32A88ED476F ON launch_token (exposition_id)');
        $this->addSql('CREATE INDEX IDX_C1E6F32A7F72333D ON launch_token (visiteur_id)');
        $this->addSql('CREATE INDEX IDX_C1E6F32AD7C740D7 ON launch_token (log_visite_id)');
        foreach (['id', 'interactif_id', 'parcours_interactif_id', 'exposition_id', 'visiteur_id', 'log_visite_id'] as $column) {
            $this->addSql("COMMENT ON COLUMN launch_token.$column IS '(DC2Type:uuid)'");
        }
        $this->addSql("COMMENT ON COLUMN launch_token.expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN launch_token.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE launch_token ADD CONSTRAINT FK_LAUNCH_TOKEN_INTERACTIF FOREIGN KEY (interactif_id) REFERENCES interactif (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE launch_token ADD CONSTRAINT FK_LAUNCH_TOKEN_PLACEMENT FOREIGN KEY (parcours_interactif_id) REFERENCES parcours_interactif (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE launch_token ADD CONSTRAINT FK_LAUNCH_TOKEN_EXPOSITION FOREIGN KEY (exposition_id) REFERENCES exposition (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE launch_token ADD CONSTRAINT FK_LAUNCH_TOKEN_VISITEUR FOREIGN KEY (visiteur_id) REFERENCES visiteur (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE launch_token ADD CONSTRAINT FK_LAUNCH_TOKEN_LOG_VISITE FOREIGN KEY (log_visite_id) REFERENCES log_visite (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE launch_token');
        $this->addSql('DROP TABLE admin_user');

        $this->addSql('CREATE TABLE parcours_interactif_old (parcours_id UUID NOT NULL, interactif_id UUID NOT NULL, PRIMARY KEY(parcours_id, interactif_id))');
        $this->addSql('INSERT INTO parcours_interactif_old (parcours_id, interactif_id) SELECT DISTINCT parcours_id, interactif_id FROM parcours_interactif');
        $this->addSql('DROP TABLE parcours_interactif');
        $this->addSql('ALTER TABLE parcours_interactif_old RENAME TO parcours_interactif');
        $this->addSql('CREATE INDEX IDX_D0E46E276E38C0DB ON parcours_interactif (parcours_id)');
        $this->addSql('CREATE INDEX IDX_D0E46E27C5D2347F ON parcours_interactif (interactif_id)');
        $this->addSql("COMMENT ON COLUMN parcours_interactif.parcours_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN parcours_interactif.interactif_id IS '(DC2Type:uuid)'");
        $this->addSql('ALTER TABLE parcours_interactif ADD CONSTRAINT FK_D0E46E276E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE parcours_interactif ADD CONSTRAINT FK_D0E46E27C5D2347F FOREIGN KEY (interactif_id) REFERENCES interactif (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}

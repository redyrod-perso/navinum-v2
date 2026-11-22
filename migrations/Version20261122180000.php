<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Gamification : règles JSON (XP, médailles) portées par chaque interactif.
 */
final class Version20261122180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Interactif.gamification : règles de gamification en JSON';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interactif ADD gamification JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interactif DROP gamification');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013114723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add media_assets focal point (focal_x/focal_y, percent) and public_path index for responsive images lookup.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_assets ADD focal_x SMALLINT UNSIGNED DEFAULT NULL, ADD focal_y SMALLINT UNSIGNED DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_media_assets_public_path ON media_assets (public_path(191))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_media_assets_public_path ON media_assets');
        $this->addSql('ALTER TABLE media_assets DROP focal_x, DROP focal_y');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add media_assets.description, folder and file_hash (deduplication) with indexes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_assets ADD description LONGTEXT DEFAULT NULL, ADD folder VARCHAR(120) DEFAULT NULL, ADD file_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_media_assets_file_hash ON media_assets (file_hash)');
        $this->addSql('CREATE INDEX idx_media_assets_folder ON media_assets (folder)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_media_assets_file_hash ON media_assets');
        $this->addSql('DROP INDEX idx_media_assets_folder ON media_assets');
        $this->addSql('ALTER TABLE media_assets DROP description, DROP folder, DROP file_hash');
    }
}

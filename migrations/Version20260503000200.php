<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Media, Menu and Lead module tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE media_assets (id VARCHAR(26) NOT NULL, original_name VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL, public_path VARCHAR(1024) NOT NULL, mime_type VARCHAR(120) NOT NULL, size INT NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_media_assets_created_at ON media_assets (created_at)');
        $this->addSql('CREATE INDEX idx_media_assets_mime_type ON media_assets (mime_type)');

        $this->addSql('CREATE TABLE menu_items (id VARCHAR(26) NOT NULL, position VARCHAR(64) NOT NULL, label VARCHAR(180) NOT NULL, url VARCHAR(1024) NOT NULL, sort_order INT NOT NULL, is_active BOOLEAN NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_menu_items_position_sort ON menu_items (position, sort_order)');

        $this->addSql('CREATE TABLE leads (id VARCHAR(26) NOT NULL, source VARCHAR(120) NOT NULL, name VARCHAR(180) NOT NULL, phone VARCHAR(40) NOT NULL, email VARCHAR(180) DEFAULT NULL, message TEXT DEFAULT NULL, consent_snapshot JSONB NOT NULL, status VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_leads_status_created_at ON leads (status, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE leads');
        $this->addSql('DROP TABLE menu_items');
        $this->addSql('DROP TABLE media_assets');
    }
}

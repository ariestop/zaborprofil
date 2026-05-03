<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503000600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SEO metadata fields to content pages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages ADD COLUMN IF NOT EXISTS meta_description VARCHAR(320) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_pages ADD COLUMN IF NOT EXISTS canonical_url VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_pages ADD COLUMN IF NOT EXISTS og_title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_pages ADD COLUMN IF NOT EXISTS og_description VARCHAR(320) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_pages ADD COLUMN IF NOT EXISTS og_image VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_pages ADD COLUMN IF NOT EXISTS og_type VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_pages ADD COLUMN IF NOT EXISTS json_ld JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages DROP COLUMN IF EXISTS meta_description');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN IF EXISTS canonical_url');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN IF EXISTS og_title');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN IF EXISTS og_description');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN IF EXISTS og_image');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN IF EXISTS og_type');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN IF EXISTS json_ld');
    }
}

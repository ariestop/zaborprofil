<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add SEO metadata columns to content_pages: meta_description, canonical_url,
 * og_title, og_description, og_image, og_type, json_ld.
 *
 * All columns are NULLable so existing rows are unaffected and the public
 * renderer falls back to defaults (no description, canonical = absolute URL of
 * Page.path, og defaults from base layout, no JSON-LD) when the editor has not
 * provided a value.
 */
final class Version20260503000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SEO metadata columns to content_pages (meta_description, canonical_url, og_*, json_ld).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE content_pages
                ADD COLUMN meta_description VARCHAR(320) DEFAULT NULL,
                ADD COLUMN canonical_url VARCHAR(2048) DEFAULT NULL,
                ADD COLUMN og_title VARCHAR(255) DEFAULT NULL,
                ADD COLUMN og_description VARCHAR(320) DEFAULT NULL,
                ADD COLUMN og_image VARCHAR(2048) DEFAULT NULL,
                ADD COLUMN og_type VARCHAR(32) DEFAULT NULL,
                ADD COLUMN json_ld JSONB DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE content_pages
                DROP COLUMN IF EXISTS json_ld,
                DROP COLUMN IF EXISTS og_type,
                DROP COLUMN IF EXISTS og_image,
                DROP COLUMN IF EXISTS og_description,
                DROP COLUMN IF EXISTS og_title,
                DROP COLUMN IF EXISTS canonical_url,
                DROP COLUMN IF EXISTS meta_description
        SQL);
    }
}

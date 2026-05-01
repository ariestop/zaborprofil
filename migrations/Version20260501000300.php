<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replaces the unconditional `uniq_content_pages_path` index with a partial
 * unique index that only applies to non-deleted rows. This lets editors
 * reuse a path after a soft delete (`deleted_at IS NOT NULL`) while still
 * preventing duplicate live paths.
 */
final class Version20260501000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace content_pages path unique index with a partial unique index ignoring soft-deleted rows.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_content_pages_path');
        $this->addSql('CREATE UNIQUE INDEX uniq_content_pages_path_active ON content_pages (path) WHERE deleted_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_content_pages_path_active');
        $this->addSql('CREATE UNIQUE INDEX uniq_content_pages_path ON content_pages (path)');
    }
}

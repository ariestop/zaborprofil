<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add content_pages indexes (status, scheduled_publish_at/scheduled_unpublish_at) for the scheduled publishing command.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_content_pages_status_publish_at ON content_pages (status, scheduled_publish_at)');
        $this->addSql('CREATE INDEX idx_content_pages_status_unpublish_at ON content_pages (status, scheduled_unpublish_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_content_pages_status_publish_at ON content_pages');
        $this->addSql('DROP INDEX idx_content_pages_status_unpublish_at ON content_pages');
    }
}

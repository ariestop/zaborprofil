<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable content_pages.meta_title (separate SEO title, falls back to title when empty).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages ADD meta_title VARCHAR(255) DEFAULT NULL AFTER visibility');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages DROP meta_title');
    }
}

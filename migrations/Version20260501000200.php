<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create content pages and page blocks tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE content_pages (id VARCHAR(26) NOT NULL, parent_id VARCHAR(26) DEFAULT NULL, type VARCHAR(32) NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(180) NOT NULL, path VARCHAR(512) NOT NULL, h1 VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, template VARCHAR(120) NOT NULL, sort_order INT NOT NULL, indexable BOOLEAN NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_content_pages_path ON content_pages (path)');
        $this->addSql('CREATE INDEX idx_content_pages_status ON content_pages (status)');
        $this->addSql('CREATE INDEX idx_content_pages_parent_id ON content_pages (parent_id)');
        $this->addSql('ALTER TABLE content_pages ADD CONSTRAINT fk_content_pages_parent_id FOREIGN KEY (parent_id) REFERENCES content_pages (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE content_page_blocks (id VARCHAR(26) NOT NULL, page_id VARCHAR(26) NOT NULL, type VARCHAR(64) NOT NULL, name VARCHAR(180) NOT NULL, position INT NOT NULL, is_enabled BOOLEAN NOT NULL, content JSONB NOT NULL, settings JSONB NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_content_page_blocks_page_position ON content_page_blocks (page_id, position)');
        $this->addSql('CREATE INDEX idx_content_page_blocks_type ON content_page_blocks (type)');
        $this->addSql('ALTER TABLE content_page_blocks ADD CONSTRAINT fk_content_page_blocks_page_id FOREIGN KEY (page_id) REFERENCES content_pages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_page_blocks DROP CONSTRAINT fk_content_page_blocks_page_id');
        $this->addSql('ALTER TABLE content_pages DROP CONSTRAINT fk_content_pages_parent_id');
        $this->addSql('DROP TABLE content_page_blocks');
        $this->addSql('DROP TABLE content_pages');
    }
}

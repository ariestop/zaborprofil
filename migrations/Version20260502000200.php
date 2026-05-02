<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create SEO redirects table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE seo_redirects (id VARCHAR(26) NOT NULL, source_path VARCHAR(512) NOT NULL, target_path VARCHAR(1024) NOT NULL, status_code INT NOT NULL, is_active BOOLEAN NOT NULL, hit_count INT NOT NULL, last_hit_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_seo_redirects_source_path ON seo_redirects (source_path)');
        $this->addSql('CREATE INDEX idx_seo_redirects_active ON seo_redirects (is_active)');
        $this->addSql('CREATE UNIQUE INDEX uniq_seo_redirects_source_path_active ON seo_redirects (source_path) WHERE is_active = true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE seo_redirects');
    }
}

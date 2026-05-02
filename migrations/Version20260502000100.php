<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create CMS settings table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE settings (id VARCHAR(26) NOT NULL, scope VARCHAR(80) NOT NULL, setting_key VARCHAR(120) NOT NULL, setting_value JSONB NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_settings_scope_key ON settings (scope, setting_key)');
        $this->addSql('CREATE INDEX idx_settings_scope ON settings (scope)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE settings');
    }
}

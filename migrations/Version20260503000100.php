<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create admin audit log entries table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_log_entries (id VARCHAR(26) NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, actor_id VARCHAR(26) DEFAULT NULL, actor_email VARCHAR(180) DEFAULT NULL, ip VARCHAR(64) DEFAULT NULL, user_agent TEXT DEFAULT NULL, request_id VARCHAR(128) DEFAULT NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(160) NOT NULL, entity_id VARCHAR(64) DEFAULT NULL, old_values JSONB NOT NULL, new_values JSONB NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_audit_log_entries_occurred_at ON audit_log_entries (occurred_at)');
        $this->addSql('CREATE INDEX idx_audit_log_entries_entity ON audit_log_entries (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_audit_log_entries_actor ON audit_log_entries (actor_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log_entries');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create admin users and messenger messages tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_users (id VARCHAR(26) NOT NULL, email VARCHAR(180) NOT NULL, roles JSONB NOT NULL, password_hash VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_admin_users_email ON admin_users (email)');

        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_messenger_messages_queue_name ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX idx_messenger_messages_available_at ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX idx_messenger_messages_delivered_at ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE admin_users');
    }
}

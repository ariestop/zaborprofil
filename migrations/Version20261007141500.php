<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007141500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CRM заявок: ответственный, нормализованный телефон для поиска, индексы списка и таблица lead_events (история и заметки).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leads ADD phone_digits VARCHAR(40) NOT NULL DEFAULT \'\', ADD assignee_id BINARY(16) DEFAULT NULL');
        $this->addSql('UPDATE leads SET phone_digits = IF(CHAR_LENGTH(REGEXP_REPLACE(phone, \'[^0-9]\', \'\')) = 11 AND REGEXP_REPLACE(phone, \'[^0-9]\', \'\') LIKE \'8%\', CONCAT(\'7\', SUBSTRING(REGEXP_REPLACE(phone, \'[^0-9]\', \'\'), 2)), REGEXP_REPLACE(phone, \'[^0-9]\', \'\'))');
        $this->addSql('ALTER TABLE leads ALTER phone_digits DROP DEFAULT');
        $this->addSql('CREATE INDEX idx_leads_created_at ON leads (created_at)');
        $this->addSql('CREATE INDEX idx_leads_source ON leads (source)');
        $this->addSql('CREATE INDEX idx_leads_assignee ON leads (assignee_id)');
        $this->addSql('CREATE INDEX idx_leads_phone_digits ON leads (phone_digits)');
        $this->addSql('CREATE TABLE lead_events (id BINARY(16) NOT NULL, lead_id BINARY(16) NOT NULL, type VARCHAR(32) NOT NULL, actor_id BINARY(16) DEFAULT NULL, actor_label VARCHAR(180) DEFAULT NULL, body LONGTEXT DEFAULT NULL, data JSON NOT NULL, created_at DATETIME NOT NULL, INDEX idx_lead_events_lead_created (lead_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lead_events ADD CONSTRAINT FK_LEAD_EVENTS_LEAD FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lead_events');
        $this->addSql('DROP INDEX idx_leads_phone_digits ON leads');
        $this->addSql('DROP INDEX idx_leads_assignee ON leads');
        $this->addSql('DROP INDEX idx_leads_source ON leads');
        $this->addSql('DROP INDEX idx_leads_created_at ON leads');
        $this->addSql('ALTER TABLE leads DROP phone_digits, DROP assignee_id');
    }
}

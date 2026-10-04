<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Leads: page URL, UTM tags, B2B flag and read marker for the CRM workspace.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leads ADD page_url VARCHAR(500) DEFAULT NULL, ADD utm JSON DEFAULT NULL, ADD is_b2b TINYINT DEFAULT 0 NOT NULL, ADD read_at DATETIME DEFAULT NULL');
        $this->addSql("UPDATE leads SET page_url = LEFT(JSON_UNQUOTE(JSON_EXTRACT(consent_snapshot, '$.pageUrl')), 500) WHERE JSON_TYPE(JSON_EXTRACT(consent_snapshot, '$.pageUrl')) = 'STRING'");
        $this->addSql("UPDATE leads SET is_b2b = 1 WHERE REGEXP_LIKE(name, '(^|[^[:alpha:]])(ООО|ИП|АО|ЗАО|ПАО|ОАО|ТОО)([^[:alpha:]]|$)', 'c')");
        $this->addSql("UPDATE leads SET read_at = updated_at WHERE status <> 'new'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leads DROP page_url, DROP utm, DROP is_b2b, DROP read_at');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Лайки работ портфолио: один лайк посетителя на работу, посетитель и IP хранятся только как HMAC.
 * Удаление работы удаляет её лайки.
 */
final class Version20261021090000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        // DDL в MySQL фиксирует транзакцию неявно.
        return false;
    }

    public function getDescription(): string
    {
        return 'Portfolio: visitor likes of projects.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portfolio_likes (id BINARY(16) NOT NULL, visitor_hash CHAR(64) NOT NULL, ip_hash CHAR(64) NOT NULL, created_at DATETIME NOT NULL, project_id BINARY(16) NOT NULL, INDEX IDX_14926438166D1F9C (project_id), INDEX idx_portfolio_likes_project_ip (project_id, ip_hash), UNIQUE INDEX uniq_portfolio_likes_project_visitor (project_id, visitor_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE portfolio_likes ADD CONSTRAINT FK_14926438166D1F9C FOREIGN KEY (project_id) REFERENCES portfolio_projects (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portfolio_likes DROP FOREIGN KEY FK_14926438166D1F9C');
        $this->addSql('DROP TABLE portfolio_likes');
    }
}

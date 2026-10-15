<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Портфолио: категории (виды изделий), работы и медиа работ (фото, загруженные видео, видео VK/Rutube).
 * Удаление работы удаляет её медиа; категорию с работами удалить нельзя (RESTRICT).
 */
final class Version20261020090000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        // DDL в MySQL фиксирует транзакцию неявно.
        return false;
    }

    public function getDescription(): string
    {
        return 'Portfolio: categories, projects and project media.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portfolio_categories (id BINARY(16) NOT NULL, slug VARCHAR(120) NOT NULL, title VARCHAR(180) NOT NULL, short_title VARCHAR(60) DEFAULT NULL, description VARCHAR(500) DEFAULT NULL, sort_order INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_portfolio_categories_slug (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE portfolio_projects (id BINARY(16) NOT NULL, slug VARCHAR(120) NOT NULL, title VARCHAR(200) NOT NULL, place VARCHAR(160) DEFAULT NULL, year INT DEFAULT NULL, summary VARCHAR(300) DEFAULT NULL, description LONGTEXT NOT NULL, specs JSON NOT NULL, status VARCHAR(16) NOT NULL, sort_order INT NOT NULL, seo_title VARCHAR(255) DEFAULT NULL, seo_description VARCHAR(320) DEFAULT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id BINARY(16) NOT NULL, INDEX IDX_2019E93C12469DE2 (category_id), INDEX idx_portfolio_projects_status_sort (status, sort_order), UNIQUE INDEX uniq_portfolio_projects_category_slug (category_id, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE portfolio_media (id BINARY(16) NOT NULL, kind VARCHAR(16) NOT NULL, src VARCHAR(1024) NOT NULL, poster VARCHAR(1024) DEFAULT NULL, caption VARCHAR(255) DEFAULT NULL, is_cover TINYINT NOT NULL, sort_order INT NOT NULL, project_id BINARY(16) NOT NULL, INDEX IDX_37748B49166D1F9C (project_id), INDEX idx_portfolio_media_project_sort (project_id, sort_order), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE portfolio_projects ADD CONSTRAINT FK_2019E93C12469DE2 FOREIGN KEY (category_id) REFERENCES portfolio_categories (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE portfolio_media ADD CONSTRAINT FK_37748B49166D1F9C FOREIGN KEY (project_id) REFERENCES portfolio_projects (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portfolio_media DROP FOREIGN KEY FK_37748B49166D1F9C');
        $this->addSql('ALTER TABLE portfolio_projects DROP FOREIGN KEY FK_2019E93C12469DE2');
        $this->addSql('DROP TABLE portfolio_media');
        $this->addSql('DROP TABLE portfolio_projects');
        $this->addSql('DROP TABLE portfolio_categories');
    }
}

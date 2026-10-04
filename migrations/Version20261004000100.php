<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

final class Version20261004000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial MySQL 8.4 schema: CMS tables, messenger queue and system page templates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_users (id BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, active TINYINT NOT NULL, roles JSON NOT NULL, password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_admin_users_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE audit_log_entries (id BINARY(16) NOT NULL, occurred_at DATETIME NOT NULL, actor_id BINARY(16) DEFAULT NULL, actor_email VARCHAR(180) DEFAULT NULL, ip VARCHAR(64) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, request_id VARCHAR(128) DEFAULT NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(160) NOT NULL, entity_id VARCHAR(64) DEFAULT NULL, old_values JSON NOT NULL, new_values JSON NOT NULL, INDEX idx_audit_log_entries_occurred_at (occurred_at), INDEX idx_audit_log_entries_entity (entity_type, entity_id), INDEX idx_audit_log_entries_actor (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_categories (id BINARY(16) NOT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, path VARCHAR(512) NOT NULL, description LONGTEXT DEFAULT NULL, sort_order INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, parent_id BINARY(16) DEFAULT NULL, INDEX IDX_8FD9B4B3727ACA70 (parent_id), INDEX idx_catalog_categories_parent_sort (parent_id, sort_order), INDEX idx_catalog_categories_active (is_active), UNIQUE INDEX uniq_catalog_categories_path (path), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_products (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(180) NOT NULL, path VARCHAR(512) NOT NULL, status VARCHAR(32) NOT NULL, summary LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, meta_description VARCHAR(320) DEFAULT NULL, canonical_url VARCHAR(2048) DEFAULT NULL, og_title VARCHAR(255) DEFAULT NULL, og_description VARCHAR(320) DEFAULT NULL, og_image VARCHAR(2048) DEFAULT NULL, is_indexable TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id BINARY(16) DEFAULT NULL, INDEX IDX_816D844412469DE2 (category_id), INDEX idx_catalog_products_category_status (category_id, status), INDEX idx_catalog_products_status (status), UNIQUE INDEX uniq_catalog_products_path (path), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_variants (id BINARY(16) NOT NULL, sku VARCHAR(80) NOT NULL, title VARCHAR(180) NOT NULL, price_cents INT NOT NULL, currency VARCHAR(3) NOT NULL, sort_order INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, product_id BINARY(16) NOT NULL, INDEX IDX_814F8DFF4584665A (product_id), INDEX idx_catalog_variants_product_sort (product_id, sort_order), INDEX idx_catalog_variants_active (is_active), UNIQUE INDEX uniq_catalog_variants_sku (sku), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE content_page_blocks (id BINARY(16) NOT NULL, type VARCHAR(64) NOT NULL, name VARCHAR(180) NOT NULL, position INT NOT NULL, is_enabled TINYINT NOT NULL, visibility VARCHAR(32) NOT NULL, content JSON NOT NULL, settings JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, page_id BINARY(16) NOT NULL, INDEX IDX_F211C9E0C4663E4 (page_id), INDEX idx_content_page_blocks_page_position (page_id, position), INDEX idx_content_page_blocks_type (type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE content_page_publications (id BINARY(16) NOT NULL, last_published_at DATETIME DEFAULT NULL, last_unpublished_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, page_id BINARY(16) NOT NULL, current_revision_id BINARY(16) DEFAULT NULL, draft_revision_id BINARY(16) DEFAULT NULL, published_revision_id BINARY(16) DEFAULT NULL, scheduled_revision_id BINARY(16) DEFAULT NULL, INDEX IDX_DF6F39FBA32ED756 (current_revision_id), INDEX IDX_DF6F39FB5CC1539E (draft_revision_id), INDEX IDX_DF6F39FBFE671D30 (published_revision_id), INDEX IDX_DF6F39FBAFD9052F (scheduled_revision_id), UNIQUE INDEX uniq_content_page_publications_page_id (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE content_page_revisions (id BINARY(16) NOT NULL, version INT NOT NULL, title VARCHAR(255) NOT NULL, h1 VARCHAR(255) NOT NULL, slug VARCHAR(180) NOT NULL, path VARCHAR(512) NOT NULL, type VARCHAR(32) NOT NULL, template VARCHAR(120) NOT NULL, status_snapshot VARCHAR(32) NOT NULL, seo_snapshot JSON NOT NULL, blocks_snapshot JSON NOT NULL, settings_snapshot JSON NOT NULL, created_by VARCHAR(26) DEFAULT NULL, created_at DATETIME NOT NULL, comment LONGTEXT DEFAULT NULL, change_summary JSON NOT NULL, page_id BINARY(16) NOT NULL, INDEX IDX_D8B4679EC4663E4 (page_id), INDEX idx_content_page_revisions_page_version (page_id, version), INDEX idx_content_page_revisions_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE content_page_templates (id BINARY(16) NOT NULL, code VARCHAR(120) NOT NULL, name VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, page_type VARCHAR(32) NOT NULL, blocks_schema JSON NOT NULL, default_seo JSON NOT NULL, default_settings JSON NOT NULL, is_system TINYINT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_content_page_templates_page_type_active (page_type, is_active), UNIQUE INDEX uniq_content_page_templates_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE content_pages (id BINARY(16) NOT NULL, type VARCHAR(32) NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(180) NOT NULL, path VARCHAR(512) NOT NULL, h1 VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, template VARCHAR(120) NOT NULL, sort_order INT NOT NULL, indexable TINYINT NOT NULL, visibility VARCHAR(32) NOT NULL, meta_description VARCHAR(320) DEFAULT NULL, canonical_url VARCHAR(2048) DEFAULT NULL, og_title VARCHAR(255) DEFAULT NULL, og_description VARCHAR(320) DEFAULT NULL, og_image VARCHAR(2048) DEFAULT NULL, og_type VARCHAR(32) DEFAULT NULL, json_ld JSON DEFAULT NULL, published_at DATETIME DEFAULT NULL, scheduled_publish_at DATETIME DEFAULT NULL, scheduled_unpublish_at DATETIME DEFAULT NULL, created_by VARCHAR(26) DEFAULT NULL, updated_by VARCHAR(26) DEFAULT NULL, published_by VARCHAR(26) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, parent_id BINARY(16) DEFAULT NULL, INDEX idx_content_pages_status (status), INDEX idx_content_pages_parent_id (parent_id), INDEX idx_content_pages_deleted_at (deleted_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE leads (id BINARY(16) NOT NULL, source VARCHAR(120) NOT NULL, name VARCHAR(180) NOT NULL, phone VARCHAR(40) NOT NULL, email VARCHAR(180) DEFAULT NULL, message LONGTEXT DEFAULT NULL, consent_snapshot JSON NOT NULL, status VARCHAR(32) NOT NULL, spam_score INT NOT NULL, spam_reasons JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_leads_status_created_at (status, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media_assets (id BINARY(16) NOT NULL, original_name VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL, public_path VARCHAR(1024) NOT NULL, mime_type VARCHAR(120) NOT NULL, size INT NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, variants JSON NOT NULL, created_at DATETIME NOT NULL, INDEX idx_media_assets_created_at (created_at), INDEX idx_media_assets_mime_type (mime_type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_items (id BINARY(16) NOT NULL, position VARCHAR(64) NOT NULL, label VARCHAR(180) NOT NULL, url VARCHAR(1024) NOT NULL, sort_order INT NOT NULL, is_active TINYINT NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_menu_items_position_sort (position, sort_order), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE seo_redirects (id BINARY(16) NOT NULL, source_path VARCHAR(512) NOT NULL, target_path VARCHAR(1024) NOT NULL, status_code INT NOT NULL, is_active TINYINT NOT NULL, hit_count INT NOT NULL, last_hit_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_seo_redirects_source_path (source_path), INDEX idx_seo_redirects_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE settings (id BINARY(16) NOT NULL, scope VARCHAR(80) NOT NULL, setting_key VARCHAR(120) NOT NULL, setting_value JSON NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_settings_scope (scope), UNIQUE INDEX uniq_settings_scope_key (scope, setting_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE catalog_categories ADD CONSTRAINT FK_8FD9B4B3727ACA70 FOREIGN KEY (parent_id) REFERENCES catalog_categories (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE catalog_products ADD CONSTRAINT FK_816D844412469DE2 FOREIGN KEY (category_id) REFERENCES catalog_categories (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE catalog_variants ADD CONSTRAINT FK_814F8DFF4584665A FOREIGN KEY (product_id) REFERENCES catalog_products (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE content_page_blocks ADD CONSTRAINT FK_F211C9E0C4663E4 FOREIGN KEY (page_id) REFERENCES content_pages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT FK_DF6F39FBC4663E4 FOREIGN KEY (page_id) REFERENCES content_pages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT FK_DF6F39FBA32ED756 FOREIGN KEY (current_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT FK_DF6F39FB5CC1539E FOREIGN KEY (draft_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT FK_DF6F39FBFE671D30 FOREIGN KEY (published_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT FK_DF6F39FBAFD9052F FOREIGN KEY (scheduled_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE content_page_revisions ADD CONSTRAINT FK_D8B4679EC4663E4 FOREIGN KEY (page_id) REFERENCES content_pages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE content_pages ADD CONSTRAINT FK_CBB7B1A7727ACA70 FOREIGN KEY (parent_id) REFERENCES content_pages (id) ON DELETE SET NULL');

        $now = '2026-05-03 00:08:00';
        foreach ($this->systemTemplates() as $template) {
            $this->addSql(
                'INSERT INTO content_page_templates (id, code, name, description, page_type, blocks_schema, default_seo, default_settings, is_system, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?)',
                [
                    $template['id'],
                    $template['code'],
                    $template['name'],
                    $template['description'],
                    $template['page_type'],
                    json_encode($template['blocks'], \JSON_THROW_ON_ERROR),
                    '{}',
                    '{}',
                    $now,
                    $now,
                ],
                [ParameterType::BINARY],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catalog_categories DROP FOREIGN KEY FK_8FD9B4B3727ACA70');
        $this->addSql('ALTER TABLE catalog_products DROP FOREIGN KEY FK_816D844412469DE2');
        $this->addSql('ALTER TABLE catalog_variants DROP FOREIGN KEY FK_814F8DFF4584665A');
        $this->addSql('ALTER TABLE content_page_blocks DROP FOREIGN KEY FK_F211C9E0C4663E4');
        $this->addSql('ALTER TABLE content_page_publications DROP FOREIGN KEY FK_DF6F39FBC4663E4');
        $this->addSql('ALTER TABLE content_page_publications DROP FOREIGN KEY FK_DF6F39FBA32ED756');
        $this->addSql('ALTER TABLE content_page_publications DROP FOREIGN KEY FK_DF6F39FB5CC1539E');
        $this->addSql('ALTER TABLE content_page_publications DROP FOREIGN KEY FK_DF6F39FBFE671D30');
        $this->addSql('ALTER TABLE content_page_publications DROP FOREIGN KEY FK_DF6F39FBAFD9052F');
        $this->addSql('ALTER TABLE content_page_revisions DROP FOREIGN KEY FK_D8B4679EC4663E4');
        $this->addSql('ALTER TABLE content_pages DROP FOREIGN KEY FK_CBB7B1A7727ACA70');
        $this->addSql('DROP TABLE admin_users');
        $this->addSql('DROP TABLE audit_log_entries');
        $this->addSql('DROP TABLE catalog_categories');
        $this->addSql('DROP TABLE catalog_products');
        $this->addSql('DROP TABLE catalog_variants');
        $this->addSql('DROP TABLE content_page_blocks');
        $this->addSql('DROP TABLE content_page_publications');
        $this->addSql('DROP TABLE content_page_revisions');
        $this->addSql('DROP TABLE content_page_templates');
        $this->addSql('DROP TABLE content_pages');
        $this->addSql('DROP TABLE leads');
        $this->addSql('DROP TABLE media_assets');
        $this->addSql('DROP TABLE menu_items');
        $this->addSql('DROP TABLE seo_redirects');
        $this->addSql('DROP TABLE settings');
        $this->addSql('DROP TABLE messenger_messages');
    }

    /**
     * @return list<array{id: string, code: string, name: string, description: string, page_type: string, blocks: list<array<string, mixed>>}>
     */
    private function systemTemplates(): array
    {
        return [
            $this->template('01H0000000000000000000000A', 'home_default', 'Home default', 'Главная страница', 'home', ['hero', 'feature_grid', 'portfolio_grid', 'steps', 'faq', 'cta_form', 'seo_text']),
            $this->template('01H0000000000000000000000B', 'service_landing', 'Service landing', 'Страница услуги', 'service', ['hero', 'text_image', 'feature_grid', 'gallery', 'price_cards', 'steps', 'portfolio_grid', 'faq', 'cta_form', 'seo_text']),
            $this->template('01H0000000000000000000000C', 'material_landing', 'Material landing', 'Материальная посадочная', 'material_landing', ['hero', 'text', 'gallery', 'feature_grid', 'table', 'price_cards', 'faq', 'cta_form', 'seo_text']),
            $this->template('01H0000000000000000000000D', 'portfolio_index', 'Portfolio index', 'Список работ', 'portfolio_index', ['hero', 'portfolio_grid', 'cta_form', 'seo_text']),
            $this->template('01H0000000000000000000000E', 'contacts', 'Contacts', 'Контакты', 'contacts', ['hero', 'contacts', 'map', 'cta_form']),
            $this->template('01H0000000000000000000000F', 'prices', 'Prices', 'Цены', 'prices', ['hero', 'table', 'price_cards', 'cta_form', 'seo_text']),
            $this->template('01H0000000000000000000000G', 'text_page', 'Text page', 'Текстовая страница', 'text_page', ['hero', 'text']),
            $this->template('01H0000000000000000000000H', 'seo_landing', 'SEO landing', 'SEO-посадочная', 'seo_landing', ['hero', 'text_image', 'feature_grid', 'faq', 'cta_form', 'seo_text']),
        ];
    }

    /**
     * @param list<string> $blocks
     *
     * @return array{id: string, code: string, name: string, description: string, page_type: string, blocks: list<array<string, mixed>>}
     */
    private function template(string $id, string $code, string $name, string $description, string $pageType, array $blocks): array
    {
        return [
            'id' => $this->identifierValue($id),
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'page_type' => $pageType,
            'blocks' => array_map(static fn (string $type, int $position): array => [
                'type' => $type,
                'name' => str_replace('_', ' ', ucfirst($type)),
                'position' => $position,
                'content' => [],
                'settings' => [],
                'isEnabled' => true,
            ], $blocks, array_keys($blocks)),
        ];
    }

    private function identifierValue(string $ulid): string
    {
        return Ulid::fromString($ulid)->toBinary();
    }
}

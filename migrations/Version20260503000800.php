<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

final class Version20260503000800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Page Engine revisions, publication state, templates, visibility and scheduling columns.';
    }

    public function up(Schema $schema): void
    {
        $identifierSqlType = $this->contentIdentifierSqlType();
        $this->addSql("ALTER TABLE content_pages ADD visibility VARCHAR(32) NOT NULL DEFAULT 'public'");
        $this->addSql('ALTER TABLE content_pages ADD scheduled_publish_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE content_pages ADD scheduled_unpublish_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE content_pages ADD created_by VARCHAR(26) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_pages ADD updated_by VARCHAR(26) DEFAULT NULL');
        $this->addSql('ALTER TABLE content_pages ADD published_by VARCHAR(26) DEFAULT NULL');
        $this->addSql("ALTER TABLE content_page_blocks ADD visibility VARCHAR(32) NOT NULL DEFAULT 'public'");

        $this->addSql("CREATE TABLE content_page_revisions (id {$identifierSqlType} NOT NULL, page_id {$identifierSqlType} NOT NULL, version INT NOT NULL, title VARCHAR(255) NOT NULL, h1 VARCHAR(255) NOT NULL, slug VARCHAR(180) NOT NULL, path VARCHAR(512) NOT NULL, type VARCHAR(32) NOT NULL, template VARCHAR(120) NOT NULL, status_snapshot VARCHAR(32) NOT NULL, seo_snapshot JSONB NOT NULL, blocks_snapshot JSONB NOT NULL, settings_snapshot JSONB NOT NULL, created_by VARCHAR(26) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, comment TEXT DEFAULT NULL, change_summary JSONB NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX idx_content_page_revisions_page_version ON content_page_revisions (page_id, version)');
        $this->addSql('CREATE INDEX idx_content_page_revisions_created_at ON content_page_revisions (created_at)');
        $this->addSql('ALTER TABLE content_page_revisions ADD CONSTRAINT fk_content_page_revisions_page_id FOREIGN KEY (page_id) REFERENCES content_pages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE content_page_publications (id {$identifierSqlType} NOT NULL, page_id {$identifierSqlType} NOT NULL, current_revision_id {$identifierSqlType} DEFAULT NULL, draft_revision_id {$identifierSqlType} DEFAULT NULL, published_revision_id {$identifierSqlType} DEFAULT NULL, scheduled_revision_id {$identifierSqlType} DEFAULT NULL, last_published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_unpublished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_content_page_publications_page_id ON content_page_publications (page_id)');
        $this->addSql('CREATE INDEX idx_content_page_publications_current_revision_id ON content_page_publications (current_revision_id)');
        $this->addSql('CREATE INDEX idx_content_page_publications_published_revision_id ON content_page_publications (published_revision_id)');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT fk_content_page_publications_page_id FOREIGN KEY (page_id) REFERENCES content_pages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT fk_content_page_publications_current_revision_id FOREIGN KEY (current_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT fk_content_page_publications_draft_revision_id FOREIGN KEY (draft_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT fk_content_page_publications_published_revision_id FOREIGN KEY (published_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT fk_content_page_publications_scheduled_revision_id FOREIGN KEY (scheduled_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE content_page_templates (id {$identifierSqlType} NOT NULL, code VARCHAR(120) NOT NULL, name VARCHAR(180) NOT NULL, description TEXT DEFAULT NULL, page_type VARCHAR(32) NOT NULL, blocks_schema JSONB NOT NULL, default_seo JSONB NOT NULL, default_settings JSONB NOT NULL, is_system BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_content_page_templates_code ON content_page_templates (code)');
        $this->addSql('CREATE INDEX idx_content_page_templates_page_type_active ON content_page_templates (page_type, is_active)');

        $now = '2026-05-03 00:08:00';
        foreach ($this->systemTemplates($identifierSqlType) as $template) {
            $this->addSql(
                'INSERT INTO content_page_templates (id, code, name, description, page_type, blocks_schema, default_seo, default_settings, is_system, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, ?::jsonb, true, true, ?, ?)',
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
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE content_page_templates');
        $this->addSql('ALTER TABLE content_page_publications DROP CONSTRAINT fk_content_page_publications_scheduled_revision_id');
        $this->addSql('ALTER TABLE content_page_publications DROP CONSTRAINT fk_content_page_publications_published_revision_id');
        $this->addSql('ALTER TABLE content_page_publications DROP CONSTRAINT fk_content_page_publications_draft_revision_id');
        $this->addSql('ALTER TABLE content_page_publications DROP CONSTRAINT fk_content_page_publications_current_revision_id');
        $this->addSql('ALTER TABLE content_page_publications DROP CONSTRAINT fk_content_page_publications_page_id');
        $this->addSql('DROP TABLE content_page_publications');
        $this->addSql('ALTER TABLE content_page_revisions DROP CONSTRAINT fk_content_page_revisions_page_id');
        $this->addSql('DROP TABLE content_page_revisions');
        $this->addSql('ALTER TABLE content_page_blocks DROP COLUMN visibility');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN published_by');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN updated_by');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN created_by');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN scheduled_unpublish_at');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN scheduled_publish_at');
        $this->addSql('ALTER TABLE content_pages DROP COLUMN visibility');
    }

    /**
     * @return list<array{id: string, code: string, name: string, description: string, page_type: string, blocks: list<array<string, mixed>>}>
     */
    private function systemTemplates(string $identifierSqlType): array
    {
        return [
            $this->template('01H0000000000000000000000A', $identifierSqlType, 'home_default', 'Home default', 'Главная страница', 'home', ['hero', 'feature_grid', 'portfolio_grid', 'steps', 'faq', 'cta_form', 'seo_text']),
            $this->template('01H0000000000000000000000B', $identifierSqlType, 'service_landing', 'Service landing', 'Страница услуги', 'service', ['hero', 'text_image', 'feature_grid', 'gallery', 'price_cards', 'steps', 'portfolio_grid', 'faq', 'cta_form', 'seo_text']),
            $this->template('01H0000000000000000000000C', $identifierSqlType, 'material_landing', 'Material landing', 'Материальная посадочная', 'material_landing', ['hero', 'text', 'gallery', 'feature_grid', 'table', 'price_cards', 'faq', 'cta_form', 'seo_text']),
            $this->template('01H0000000000000000000000D', $identifierSqlType, 'portfolio_index', 'Portfolio index', 'Список работ', 'portfolio_index', ['hero', 'portfolio_grid', 'cta_form', 'seo_text']),
            $this->template('01H0000000000000000000000E', $identifierSqlType, 'contacts', 'Contacts', 'Контакты', 'contacts', ['hero', 'contacts', 'map', 'cta_form']),
            $this->template('01H0000000000000000000000F', $identifierSqlType, 'prices', 'Prices', 'Цены', 'prices', ['hero', 'table', 'price_cards', 'cta_form', 'seo_text']),
            $this->template('01H0000000000000000000000G', $identifierSqlType, 'text_page', 'Text page', 'Текстовая страница', 'text_page', ['hero', 'text']),
            $this->template('01H0000000000000000000000H', $identifierSqlType, 'seo_landing', 'SEO landing', 'SEO-посадочная', 'seo_landing', ['hero', 'text_image', 'feature_grid', 'faq', 'cta_form', 'seo_text']),
        ];
    }

    /**
     * @param list<string> $blocks
     *
     * @return array{id: string, code: string, name: string, description: string, page_type: string, blocks: list<array<string, mixed>>}
     */
    private function template(string $id, string $identifierSqlType, string $code, string $name, string $description, string $pageType, array $blocks): array
    {
        return [
            'id' => $this->identifierValue($id, $identifierSqlType),
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

    private function contentIdentifierSqlType(): string
    {
        $dataType = $this->connection->fetchOne(
            "SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'content_pages' AND column_name = 'id'",
        );

        return $dataType === 'uuid' ? 'UUID' : 'VARCHAR(26)';
    }

    private function identifierValue(string $ulid, string $identifierSqlType): string
    {
        return $identifierSqlType === 'UUID' ? Ulid::fromString($ulid)->toRfc4122() : $ulid;
    }
}

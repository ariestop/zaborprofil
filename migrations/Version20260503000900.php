<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503000900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert Doctrine ULID columns from VARCHAR(26) to native PostgreSQL UUID.';
    }

    public function up(Schema $schema): void
    {
        $this->dropForeignKeys();
        $this->createUlidToUuidFunction();

        foreach ($this->ulidColumns() as [$table, $column]) {
            $this->addSql(\sprintf('ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT', $table, $column));
            $this->addSql(\sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE UUID USING app_ulid_to_uuid(%s)',
                $table,
                $column,
                $column,
            ));
        }

        $this->dropUlidToUuidFunction();
        $this->addForeignKeys();
    }

    public function down(Schema $schema): void
    {
        $this->dropForeignKeys();
        $this->createUuidToUlidFunction();

        foreach ($this->ulidColumns() as [$table, $column]) {
            $this->addSql(\sprintf('ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT', $table, $column));
            $this->addSql(\sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE VARCHAR(26) USING app_uuid_to_ulid(%s)',
                $table,
                $column,
                $column,
            ));
        }

        $this->dropUuidToUlidFunction();
        $this->addForeignKeys();
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function ulidColumns(): array
    {
        return [
            ['admin_users', 'id'],
            ['audit_log_entries', 'id'],
            ['audit_log_entries', 'actor_id'],
            ['media_assets', 'id'],
            ['menu_items', 'id'],
            ['leads', 'id'],
            ['settings', 'id'],
            ['seo_redirects', 'id'],
            ['catalog_categories', 'id'],
            ['catalog_categories', 'parent_id'],
            ['catalog_products', 'id'],
            ['catalog_products', 'category_id'],
            ['catalog_variants', 'id'],
            ['catalog_variants', 'product_id'],
            ['content_pages', 'id'],
            ['content_pages', 'parent_id'],
            ['content_page_blocks', 'id'],
            ['content_page_blocks', 'page_id'],
            ['content_page_revisions', 'id'],
            ['content_page_revisions', 'page_id'],
            ['content_page_publications', 'id'],
            ['content_page_publications', 'page_id'],
            ['content_page_publications', 'current_revision_id'],
            ['content_page_publications', 'draft_revision_id'],
            ['content_page_publications', 'published_revision_id'],
            ['content_page_publications', 'scheduled_revision_id'],
            ['content_page_templates', 'id'],
        ];
    }

    private function dropForeignKeys(): void
    {
        foreach ($this->foreignKeys() as [$table, $constraint]) {
            $this->addSql(\sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $constraint));
        }
    }

    private function addForeignKeys(): void
    {
        $this->addSql('ALTER TABLE content_pages ADD CONSTRAINT fk_content_pages_parent_id FOREIGN KEY (parent_id) REFERENCES content_pages (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_page_blocks ADD CONSTRAINT fk_content_page_blocks_page_id FOREIGN KEY (page_id) REFERENCES content_pages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE catalog_categories ADD CONSTRAINT fk_catalog_categories_parent FOREIGN KEY (parent_id) REFERENCES catalog_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE catalog_products ADD CONSTRAINT fk_catalog_products_category FOREIGN KEY (category_id) REFERENCES catalog_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE catalog_variants ADD CONSTRAINT fk_catalog_variants_product FOREIGN KEY (product_id) REFERENCES catalog_products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_page_revisions ADD CONSTRAINT fk_content_page_revisions_page_id FOREIGN KEY (page_id) REFERENCES content_pages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT fk_content_page_publications_page_id FOREIGN KEY (page_id) REFERENCES content_pages (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT fk_content_page_publications_current_revision_id FOREIGN KEY (current_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT fk_content_page_publications_draft_revision_id FOREIGN KEY (draft_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT fk_content_page_publications_published_revision_id FOREIGN KEY (published_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE content_page_publications ADD CONSTRAINT fk_content_page_publications_scheduled_revision_id FOREIGN KEY (scheduled_revision_id) REFERENCES content_page_revisions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function foreignKeys(): array
    {
        return [
            ['content_page_publications', 'fk_content_page_publications_scheduled_revision_id'],
            ['content_page_publications', 'fk_content_page_publications_published_revision_id'],
            ['content_page_publications', 'fk_content_page_publications_draft_revision_id'],
            ['content_page_publications', 'fk_content_page_publications_current_revision_id'],
            ['content_page_publications', 'fk_content_page_publications_page_id'],
            ['content_page_revisions', 'fk_content_page_revisions_page_id'],
            ['catalog_variants', 'fk_catalog_variants_product'],
            ['catalog_products', 'fk_catalog_products_category'],
            ['catalog_categories', 'fk_catalog_categories_parent'],
            ['content_page_blocks', 'fk_content_page_blocks_page_id'],
            ['content_pages', 'fk_content_pages_parent_id'],
        ];
    }

    private function createUlidToUuidFunction(): void
    {
        $this->addSql(<<<'SQL'
CREATE OR REPLACE FUNCTION app_ulid_to_uuid(value text) RETURNS uuid AS $$
DECLARE
    alphabet text := '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    digit int;
    hex text := '';
    n numeric := 0;
    byte_value int;
    i int;
BEGIN
    IF length(value) = 36 THEN
        RETURN value::uuid;
    END IF;

    IF length(value) <> 26 THEN
        RAISE EXCEPTION 'Invalid ULID length: %', value;
    END IF;

    FOR i IN 1..26 LOOP
        digit := position(upper(substr(value, i, 1)) in alphabet) - 1;
        IF digit < 0 THEN
            RAISE EXCEPTION 'Invalid ULID character in value: %', value;
        END IF;

        n := n * 32 + digit;
    END LOOP;

    FOR i IN REVERSE 15..0 LOOP
        byte_value := floor(n / power(256::numeric, i))::int;
        hex := hex || lpad(to_hex(byte_value), 2, '0');
        n := n - byte_value * power(256::numeric, i);
    END LOOP;

    RETURN (substr(hex, 1, 8) || '-' || substr(hex, 9, 4) || '-' || substr(hex, 13, 4) || '-' || substr(hex, 17, 4) || '-' || substr(hex, 21, 12))::uuid;
END;
$$ LANGUAGE plpgsql IMMUTABLE STRICT
SQL);
    }

    private function dropUlidToUuidFunction(): void
    {
        $this->addSql('DROP FUNCTION app_ulid_to_uuid(text)');
    }

    private function createUuidToUlidFunction(): void
    {
        $this->addSql(<<<'SQL'
CREATE OR REPLACE FUNCTION app_uuid_to_ulid(value uuid) RETURNS varchar(26) AS $$
DECLARE
    alphabet text := '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    hex text := replace(value::text, '-', '');
    digit int;
    n numeric := 0;
    encoded text := '';
    i int;
BEGIN
    FOR i IN 1..32 LOOP
        digit := position(substr(hex, i, 1) in '0123456789abcdef') - 1;
        n := n * 16 + digit;
    END LOOP;

    FOR i IN 1..26 LOOP
        digit := mod(n, 32)::int;
        encoded := substr(alphabet, digit + 1, 1) || encoded;
        n := floor(n / 32);
    END LOOP;

    RETURN encoded;
END;
$$ LANGUAGE plpgsql IMMUTABLE STRICT
SQL);
    }

    private function dropUuidToUlidFunction(): void
    {
        $this->addSql('DROP FUNCTION app_uuid_to_ulid(uuid)');
    }
}

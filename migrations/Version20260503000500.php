<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503000500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create catalog categories, products and variants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE catalog_categories (id VARCHAR(26) NOT NULL, parent_id VARCHAR(26) DEFAULT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, path VARCHAR(512) NOT NULL, description TEXT DEFAULT NULL, sort_order INT NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_catalog_categories_path ON catalog_categories (path)');
        $this->addSql('CREATE INDEX idx_catalog_categories_parent_sort ON catalog_categories (parent_id, sort_order)');
        $this->addSql('CREATE INDEX idx_catalog_categories_active ON catalog_categories (is_active)');
        $this->addSql('ALTER TABLE catalog_categories ADD CONSTRAINT fk_catalog_categories_parent FOREIGN KEY (parent_id) REFERENCES catalog_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE catalog_products (id VARCHAR(26) NOT NULL, category_id VARCHAR(26) DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(180) NOT NULL, path VARCHAR(512) NOT NULL, status VARCHAR(32) NOT NULL, summary TEXT DEFAULT NULL, description TEXT DEFAULT NULL, is_indexable BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_catalog_products_path ON catalog_products (path)');
        $this->addSql('CREATE INDEX idx_catalog_products_category_status ON catalog_products (category_id, status)');
        $this->addSql('CREATE INDEX idx_catalog_products_status ON catalog_products (status)');
        $this->addSql('ALTER TABLE catalog_products ADD CONSTRAINT fk_catalog_products_category FOREIGN KEY (category_id) REFERENCES catalog_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE catalog_variants (id VARCHAR(26) NOT NULL, product_id VARCHAR(26) NOT NULL, sku VARCHAR(80) NOT NULL, title VARCHAR(180) NOT NULL, price_cents INT NOT NULL, currency VARCHAR(3) NOT NULL, sort_order INT NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_catalog_variants_sku ON catalog_variants (sku)');
        $this->addSql('CREATE INDEX idx_catalog_variants_product_sort ON catalog_variants (product_id, sort_order)');
        $this->addSql('CREATE INDEX idx_catalog_variants_active ON catalog_variants (is_active)');
        $this->addSql('ALTER TABLE catalog_variants ADD CONSTRAINT fk_catalog_variants_product FOREIGN KEY (product_id) REFERENCES catalog_products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE catalog_variants');
        $this->addSql('DROP TABLE catalog_products');
        $this->addSql('DROP TABLE catalog_categories');
    }
}

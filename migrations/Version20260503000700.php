<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503000700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SEO metadata fields to catalog products.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catalog_products ADD meta_description VARCHAR(320) DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD canonical_url VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD og_title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD og_description VARCHAR(320) DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD og_image VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catalog_products DROP meta_description');
        $this->addSql('ALTER TABLE catalog_products DROP canonical_url');
        $this->addSql('ALTER TABLE catalog_products DROP og_title');
        $this->addSql('ALTER TABLE catalog_products DROP og_description');
        $this->addSql('ALTER TABLE catalog_products DROP og_image');
    }
}

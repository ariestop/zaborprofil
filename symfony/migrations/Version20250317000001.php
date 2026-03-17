<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250317000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema for Zaborprofil platform';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id BIGINT AUTO_INCREMENT NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            name VARCHAR(255) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL,
            organization_id BIGINT DEFAULT NULL,
            price_profile_id BIGINT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE organizations (
            id BIGINT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            price_profile_id BIGINT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE categories (
            id BIGINT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            UNIQUE INDEX UNIQ_3AF34668989D9B62 (slug),
            INDEX idx_categories_slug (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE products (
            id BIGINT AUTO_INCREMENT NOT NULL,
            category_id BIGINT NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_B3BA5A5A989D9B62 (slug),
            INDEX idx_products_category_slug (category_id, slug),
            PRIMARY KEY(id),
            CONSTRAINT FK_B3BA5A5A12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE product_variants (
            id BIGINT AUTO_INCREMENT NOT NULL,
            product_id BIGINT NOT NULL,
            sku VARCHAR(100) NOT NULL,
            attributes JSON NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            CONSTRAINT FK_2187C1364584665A FOREIGN KEY (product_id) REFERENCES products (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE price_profiles (
            id BIGINT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL,
            slug VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE price_lists (
            id BIGINT AUTO_INCREMENT NOT NULL,
            price_profile_id BIGINT NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) DEFAULT NULL,
            currency VARCHAR(3) DEFAULT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_price_lists_profile FOREIGN KEY (price_profile_id) REFERENCES price_profiles (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE price_rules (
            id BIGINT AUTO_INCREMENT NOT NULL,
            product_id BIGINT NOT NULL,
            price_list_id BIGINT NOT NULL,
            base_price DECIMAL(12, 2) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_price_rules_product_list (product_id, price_list_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_price_rules_product FOREIGN KEY (product_id) REFERENCES products (id),
            CONSTRAINT FK_price_rules_list FOREIGN KEY (price_list_id) REFERENCES price_lists (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE configurations (
            id BIGINT AUTO_INCREMENT NOT NULL,
            user_id BIGINT DEFAULT NULL,
            guest_token VARCHAR(255) DEFAULT NULL,
            config_data JSON NOT NULL,
            version INT DEFAULT 1 NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_configurations_guest (guest_token),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE estimates (
            id BIGINT AUTO_INCREMENT NOT NULL,
            user_id BIGINT DEFAULT NULL,
            configuration_id BIGINT NOT NULL,
            total_price DECIMAL(12, 2) NOT NULL,
            status VARCHAR(50) DEFAULT \'draft\' NOT NULL,
            expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_estimates_user_created (user_id, created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE estimate_lines (
            id BIGINT AUTO_INCREMENT NOT NULL,
            estimate_id BIGINT NOT NULL,
            description VARCHAR(255) NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(12, 2) NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_estimate_lines_estimate FOREIGN KEY (estimate_id) REFERENCES estimates (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE orders (
            id BIGINT AUTO_INCREMENT NOT NULL,
            user_id BIGINT DEFAULT NULL,
            organization_id BIGINT DEFAULT NULL,
            estimate_id BIGINT DEFAULT NULL,
            status VARCHAR(50) NOT NULL,
            total_price DECIMAL(12, 2) NOT NULL,
            contact_name VARCHAR(255) DEFAULT NULL,
            contact_email VARCHAR(255) DEFAULT NULL,
            contact_phone VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_orders_status_created (status, created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE order_items (
            id BIGINT AUTO_INCREMENT NOT NULL,
            order_id BIGINT NOT NULL,
            product_id BIGINT NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(12, 2) NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id),
            CONSTRAINT FK_order_items_product FOREIGN KEY (product_id) REFERENCES products (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE documents (
            id BIGINT AUTO_INCREMENT NOT NULL,
            type VARCHAR(50) NOT NULL,
            owner_type VARCHAR(50) NOT NULL,
            owner_id BIGINT NOT NULL,
            file_url LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql('CREATE TABLE leads (
            id BIGINT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL,
            message LONGTEXT DEFAULT NULL,
            source VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE leads');
        $this->addSql('DROP TABLE documents');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE estimate_lines');
        $this->addSql('DROP TABLE estimates');
        $this->addSql('DROP TABLE configurations');
        $this->addSql('DROP TABLE price_rules');
        $this->addSql('DROP TABLE price_lists');
        $this->addSql('DROP TABLE price_profiles');
        $this->addSql('DROP TABLE product_variants');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE organizations');
        $this->addSql('DROP TABLE users');
    }
}

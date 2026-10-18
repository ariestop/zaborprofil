<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Меню сайта становится двухуровневым: у пункта может быть раздел-родитель (`parent_id`, та же позиция)
 * и короткая подпись (`hint`) для раскрытого меню. Существующие пункты остаются на верхнем уровне.
 */
final class Version20261019090000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        // DDL в MySQL фиксирует транзакцию неявно.
        return false;
    }

    public function getDescription(): string
    {
        return 'Menu items: parent_id (two-level menu) and hint.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_items ADD parent_id BINARY(16) DEFAULT NULL, ADD hint VARCHAR(160) DEFAULT NULL, ADD INDEX idx_menu_items_parent (parent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_items DROP INDEX idx_menu_items_parent, DROP parent_id, DROP hint');
    }
}

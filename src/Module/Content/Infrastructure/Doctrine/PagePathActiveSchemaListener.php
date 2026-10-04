<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Описывает в «ожидаемой» схеме Doctrine generated column `content_pages.path_active`
 * и unique-индекс `uniq_content_pages_path_active`.
 *
 * Атрибуты ORM не умеют выражать generated column, а в MySQL нет partial unique index,
 * поэтому уникальность `path` среди живых страниц обеспечивается индексом по колонке
 * `IF(deleted_at IS NULL, path, NULL)` (создана в Version20261004000100). Без этого
 * listener-а `doctrine:schema:validate` и `doctrine:migrations:diff` считали бы колонку
 * и индекс «лишними» и предлагали их удалить. Заодно `SchemaTool` в тестах создаёт то же
 * ограничение, что и миграции.
 *
 * Компаратор DBAL игнорирует `columnDefinition`, поэтому сравниваются тип, длина и
 * nullable колонки, а также состав и уникальность индекса.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class PagePathActiveSchemaListener
{
    public const string TABLE = 'content_pages';

    public const string COLUMN = 'path_active';

    public const string INDEX = 'uniq_content_pages_path_active';

    private const string COLUMN_DEFINITION = 'VARCHAR(512) GENERATED ALWAYS AS (IF(deleted_at IS NULL, path, NULL)) VIRTUAL';

    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();

        if (!$schema->hasTable(self::TABLE)) {
            return;
        }

        $table = $schema->getTable(self::TABLE);

        if (!$table->hasColumn(self::COLUMN)) {
            $table->addColumn(self::COLUMN, 'string', [
                'length' => 512,
                'notnull' => false,
                'columnDefinition' => self::COLUMN_DEFINITION,
            ]);
        }

        if (!$table->hasIndex(self::INDEX)) {
            $table->addUniqueIndex([self::COLUMN], self::INDEX);
        }
    }
}

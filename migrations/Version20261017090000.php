<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

/**
 * Раньше `content_pages.updated_by` почти не заполнялся. Для существующих страниц автор последней
 * правки восстанавливается по журналу действий: последняя запись администратора о самой странице
 * или о любом её блоке. Страницы без таких записей остаются без автора.
 */
final class Version20261017090000 extends AbstractMigration
{
    /** Так журнал действий записывает тип сущности (`AuditLogEntry::entityType`). */
    private const string PAGE_ENTITY = Page::class;

    private const string BLOCK_ENTITY = PageBlock::class;

    public function getDescription(): string
    {
        return 'Content pages: fill updated_by from the audit log for pages without a recorded editor.';
    }

    public function up(Schema $schema): void
    {
        $pagesWithoutEditor = [];
        foreach ($this->connection->fetchFirstColumn('SELECT id FROM content_pages WHERE updated_by IS NULL') as $id) {
            $pagesWithoutEditor[$this->ulid($id)] = $id;
        }

        $blockPages = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, page_id FROM content_page_blocks') as $row) {
            $blockPages[$this->ulid($row['id'])] = $this->ulid($row['page_id']);
        }

        $editors = [];
        $entries = $this->connection->fetchAllAssociative(
            'SELECT entity_type, entity_id, actor_id FROM audit_log_entries WHERE actor_id IS NOT NULL AND entity_id IS NOT NULL AND entity_type IN (?, ?) ORDER BY occurred_at, id',
            [self::PAGE_ENTITY, self::BLOCK_ENTITY],
        );
        foreach ($entries as $entry) {
            $entityId = \is_string($entry['entity_id']) ? $entry['entity_id'] : '';
            $pageId = $entry['entity_type'] === self::PAGE_ENTITY ? $entityId : ($blockPages[$entityId] ?? null);
            if ($pageId !== null && isset($pagesWithoutEditor[$pageId])) {
                $editors[$pageId] = $this->ulid($entry['actor_id']);
            }
        }

        foreach ($editors as $pageId => $editorId) {
            $this->addSql(
                'UPDATE content_pages SET updated_by = ? WHERE id = ? AND updated_by IS NULL',
                [$editorId, $pagesWithoutEditor[$pageId]],
                [ParameterType::STRING, ParameterType::BINARY],
            );
        }

        // Пустая база (CI, новый стенд): миграция всё равно должна выполнить хотя бы один запрос.
        $this->addSql('UPDATE content_pages SET updated_by = updated_by WHERE 1 = 0');
    }

    public function down(Schema $schema): void
    {
        // Восстановленные авторы не откатываются: до миграции поле было пустым и ничего не значило.
        $this->addSql('UPDATE content_pages SET updated_by = updated_by WHERE 1 = 0');
    }

    private function ulid(mixed $binary): string
    {
        if (!\is_string($binary)) {
            throw new \UnexpectedValueException('BINARY(16) ULID column expected.');
        }

        return Ulid::fromBinary($binary)->toBase32();
    }
}

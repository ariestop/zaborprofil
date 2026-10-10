<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Номер версии ревизии вычисляется как MAX(version) + 1, поэтому два одновременных сохранения одной страницы
 * могли получить одинаковый номер. Уникальный индекс `(page_id, version)` закрывает эту гонку.
 *
 * Если дубли уже есть, повторяющиеся ревизии (кроме самой ранней) получают следующие свободные номера
 * той же страницы: содержимое ревизий не меняется, порядок по времени создания сохраняется.
 */
final class Version20261018090000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        // DDL в MySQL фиксирует транзакцию неявно.
        return false;
    }

    public function getDescription(): string
    {
        return 'Content page revisions: unique (page_id, version); renumber duplicate versions if any.';
    }

    public function up(Schema $schema): void
    {
        $duplicatePages = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT page_id FROM content_page_revisions GROUP BY page_id, version HAVING COUNT(*) > 1',
        );

        foreach ($duplicatePages as $pageId) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, version FROM content_page_revisions WHERE page_id = ? ORDER BY version, created_at, id',
                [$pageId],
                [ParameterType::BINARY],
            );
            $nextVersion = max(array_map(static fn (array $row): int => (int) $row['version'], $rows)) + 1;
            $seen = [];
            foreach ($rows as $row) {
                $version = (int) $row['version'];
                if (!isset($seen[$version])) {
                    $seen[$version] = true;

                    continue;
                }

                $this->addSql(
                    'UPDATE content_page_revisions SET version = ? WHERE id = ?',
                    [$nextVersion++, $row['id']],
                    [ParameterType::INTEGER, ParameterType::BINARY],
                );
            }
        }

        $this->addSql('DROP INDEX idx_content_page_revisions_page_version ON content_page_revisions');
        $this->addSql('CREATE UNIQUE INDEX uniq_content_page_revisions_page_version ON content_page_revisions (page_id, version)');
    }

    public function down(Schema $schema): void
    {
        // Перенумерованные дубли не возвращаются: прежние номера совпадали и ничего не различали.
        $this->addSql('DROP INDEX uniq_content_page_revisions_page_version ON content_page_revisions');
        $this->addSql('CREATE INDEX idx_content_page_revisions_page_version ON content_page_revisions (page_id, version)');
    }
}

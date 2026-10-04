<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Repository;

use App\Module\Content\Domain\Entity\Page;
use DateTimeImmutable;

/**
 * Repository ports use string identifiers (ULID-formatted) so the Domain
 * layer does not leak `Symfony\Component\Uid\Ulid`. Conversion happens in
 * the Doctrine adapter only.
 */
interface PageRepositoryInterface
{
    public function save(Page $page): void;

    public function get(string $id): Page;

    public function findById(string $id): ?Page;

    public function findPreviewById(string $id): ?Page;

    public function findOneByPath(string $path): ?Page;

    public function findPublishedByPath(string $path): ?Page;

    public function existsByPath(string $path, ?string $excludeId = null): bool;

    /**
     * Paths of other published pages whose effective SEO title
     * (`metaTitle`, falling back to `title`) equals the given one.
     *
     * @return list<string>
     */
    public function findPublishedPathsBySeoTitle(string $seoTitle, string $excludeId, int $limit = 5): array;

    /**
     * @return list<Page>
     */
    public function findAllForAdmin(): array;

    /**
     * Страница списка для админки: поиск по названию и адресу, фильтр по статусу, пагинация на стороне БД.
     */
    public function searchForAdmin(PageSearchCriteria $criteria): PageSearchResult;

    public function countPublishedIndexable(): int;

    /**
     * Returns every published, non-deleted, indexable page in stable order so
     * the sitemap stays deterministic.
     *
     * @return list<Page>
     */
    public function findAllPublishedIndexable(): array;

    /**
     * @return list<Page>
     */
    public function findPublishedIndexableSlice(int $limit, int $offset): array;

    /**
     * Запланированные страницы, у которых наступило время публикации. Самые ранние первыми.
     *
     * @return list<Page>
     */
    public function findDueForScheduledPublish(DateTimeImmutable $now, int $limit): array;

    /**
     * Опубликованные страницы, у которых наступило время снятия с публикации.
     *
     * @return list<Page>
     */
    public function findDueForScheduledUnpublish(DateTimeImmutable $now, int $limit): array;

    /**
     * Сколько запланированных операций просрочено на момент `$threshold` (индикатор остановленного планировщика).
     */
    public function countOverdueSchedules(DateTimeImmutable $threshold): int;
}

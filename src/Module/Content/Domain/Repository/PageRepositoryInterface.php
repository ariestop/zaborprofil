<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Repository;

use App\Module\Content\Domain\Entity\Page;

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
     * @return list<Page>
     */
    public function findAllForAdmin(): array;

    /**
     * Returns every published, non-deleted, indexable page in stable order so
     * the sitemap stays deterministic.
     *
     * @return list<Page>
     */
    public function findAllPublishedIndexable(): array;
}

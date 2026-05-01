<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Repository;

use App\Module\Content\Domain\Entity\PageBlock;

interface PageBlockRepositoryInterface
{
    public function save(PageBlock $block): void;

    /**
     * Persists every block and commits them atomically in a single
     * transaction so reorder/batch use cases never leave partial updates.
     *
     * @param iterable<PageBlock> $blocks
     */
    public function saveAll(iterable $blocks): void;

    public function remove(PageBlock $block): void;

    public function get(string $id): PageBlock;

    public function findById(string $id): ?PageBlock;

    /**
     * @return list<PageBlock>
     */
    public function findByPage(string $pageId): array;
}

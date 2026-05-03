<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Repository;

use App\Module\Content\Domain\Entity\PageRevision;

interface PageRevisionRepositoryInterface
{
    public function save(PageRevision $revision): void;

    public function get(string $id): PageRevision;

    public function nextVersionForPage(string $pageId): int;

    /**
     * @return list<PageRevision>
     */
    public function findByPage(string $pageId): array;
}

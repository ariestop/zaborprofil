<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Repository;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PagePublication;

interface PagePublicationRepositoryInterface
{
    public function save(PagePublication $publication): void;

    public function getOrCreate(Page $page): PagePublication;

    public function findByPage(string $pageId): ?PagePublication;

    /**
     * Публикации сразу нескольких страниц вместе с опубликованными ревизиями — одним запросом.
     *
     * @param list<string> $pageIds
     *
     * @return array<string, PagePublication> id страницы => публикация (страницы без публикации отсутствуют)
     */
    public function findByPages(array $pageIds): array;

    public function findPublishedByPath(string $path): ?PagePublication;
}

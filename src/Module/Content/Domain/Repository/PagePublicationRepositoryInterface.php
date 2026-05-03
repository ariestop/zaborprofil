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

    public function findPublishedByPath(string $path): ?PagePublication;
}

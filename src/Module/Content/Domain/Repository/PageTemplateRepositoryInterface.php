<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Repository;

use App\Module\Content\Domain\Entity\PageTemplate;
use App\Module\Content\Domain\Enum\PageType;

interface PageTemplateRepositoryInterface
{
    public function save(PageTemplate $template): void;

    public function getByCode(string $code): PageTemplate;

    /**
     * @return list<PageTemplate>
     */
    public function findActive(?PageType $pageType = null): array;
}

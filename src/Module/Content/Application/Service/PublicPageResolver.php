<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Repository\PageRepositoryInterface;

final readonly class PublicPageResolver
{
    public function __construct(private PageRepositoryInterface $pages)
    {
    }

    public function resolve(string $path): ?PublicPageView
    {
        $page = $this->pages->findPublishedByPath($this->normalizePath($path));

        return $page === null ? null : PublicPageView::fromPage($page);
    }

    private function normalizePath(string $path): string
    {
        $normalized = trim($path);

        if ($normalized === '') {
            return '/';
        }

        if (!str_starts_with($normalized, '/')) {
            return '/' . $normalized;
        }

        return $normalized;
    }
}

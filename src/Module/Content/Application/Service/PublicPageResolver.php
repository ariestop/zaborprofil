<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Repository\PageRepositoryInterface;

final readonly class PublicPageResolver implements PublicPageResolverInterface
{
    public function __construct(private PageRepositoryInterface $pages)
    {
    }

    public function resolve(string $path): ?PublicPageView
    {
        $page = $this->pages->findPublishedByPath(PublicPagePathNormalizer::normalize($path));

        return $page === null ? null : PublicPageView::fromPage($page);
    }
}

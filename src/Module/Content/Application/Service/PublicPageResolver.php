<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;

final readonly class PublicPageResolver implements PublicPageResolverInterface
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PagePublicationRepositoryInterface $publications,
    ) {
    }

    public function resolve(string $path): ?PublicPageView
    {
        $normalized = PublicPagePathNormalizer::normalize($path);
        $publication = $this->publications->findPublishedByPath($normalized);
        if ($publication?->publishedRevision() !== null) {
            return PublicPageView::fromRevision($publication->publishedRevision());
        }

        $page = $this->pages->findPublishedByPath($normalized);
        return $page === null ? null : PublicPageView::fromPage($page);
    }
}

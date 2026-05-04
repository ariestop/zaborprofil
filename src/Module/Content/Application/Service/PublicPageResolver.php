<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;

final readonly class PublicPageResolver implements PublicPageResolverInterface
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PagePublicationRepositoryInterface $publications,
        private PageBlockRepositoryInterface $blocks,
    ) {
    }

    public function resolve(string $path): ?PublicPageView
    {
        $normalized = PublicPagePathNormalizer::normalize($path);
        $publication = $this->publications->findPublishedByPath($normalized);
        if ($publication?->publishedRevision() !== null) {
            $page = $publication->page();
            $liveBlocks = $this->blocks->findByPage((string) $page->id());

            return PublicPageView::fromRevisionUsingLiveBlocks($publication->publishedRevision(), $liveBlocks);
        }

        $page = $this->pages->findPublishedByPath($normalized);
        return $page === null ? null : PublicPageView::fromPage($page);
    }
}

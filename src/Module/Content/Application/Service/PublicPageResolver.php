<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Settings\Application\Service\SettingsRegistry;

final readonly class PublicPageResolver implements PublicPageResolverInterface
{
    private const string SETTINGS_SCOPE = 'content';
    private const string SETTINGS_KEY_BLOCKS_SOURCE = 'public_page_blocks_source';
    private const string BLOCKS_SOURCE_LIVE = 'live';
    private const string BLOCKS_SOURCE_SNAPSHOT = 'snapshot';

    public function __construct(
        private PageRepositoryInterface $pages,
        private PagePublicationRepositoryInterface $publications,
        private SettingsRegistry $settings,
    ) {
    }

    public function resolve(string $path): ?PublicPageView
    {
        $normalized = PublicPagePathNormalizer::normalize($path);
        $publication = $this->publications->findPublishedByPath($normalized);

        if ($this->usesLiveBlocks()) {
            $page = $this->pages->findPublishedByPath($normalized);
            if ($page === null) {
                return null;
            }

            if ($publication?->publishedRevision() !== null) {
                return PublicPageView::fromRevisionUsingLiveBlocks($publication->publishedRevision(), $page->enabledBlocks());
            }

            return PublicPageView::fromPage($page);
        }

        if ($publication?->publishedRevision() !== null) {
            return PublicPageView::fromRevision($publication->publishedRevision());
        }

        $page = $this->pages->findPublishedByPath($normalized);
        return $page === null ? null : PublicPageView::fromPage($page);
    }

    private function usesLiveBlocks(): bool
    {
        $source = $this->settings->getString(self::SETTINGS_SCOPE, self::SETTINGS_KEY_BLOCKS_SOURCE, self::BLOCKS_SOURCE_SNAPSHOT);

        return $source === self::BLOCKS_SOURCE_LIVE;
    }
}

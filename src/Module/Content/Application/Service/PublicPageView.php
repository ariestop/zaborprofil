<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageRevision;

final readonly class PublicPageView
{
    /**
     * @param list<PageBlockView>             $blocks
     * @param list<array<string, mixed>>|null $jsonLd
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $h1,
        public string $path,
        public string $template,
        public bool $isIndexable,
        public ?string $metaDescription,
        public ?string $canonicalUrl,
        public ?string $ogTitle,
        public ?string $ogDescription,
        public ?string $ogImage,
        public ?string $ogType,
        public ?array $jsonLd,
        public array $blocks,
    ) {
    }

    public static function fromPage(Page $page): self
    {
        return new self(
            (string) $page->id(),
            $page->title(),
            $page->h1(),
            $page->path(),
            $page->template(),
            $page->isIndexable(),
            $page->metaDescription(),
            $page->canonicalUrl(),
            $page->ogTitle(),
            $page->ogDescription(),
            $page->ogImage(),
            $page->ogType(),
            $page->jsonLd(),
            array_map(PageBlockView::fromBlock(...), $page->enabledBlocks()),
        );
    }

    public static function fromRevision(PageRevision $revision): self
    {
        $seo = $revision->seoSnapshot();
        $normalizer = new SnapshotValueNormalizer();
        $metaDescription = \is_string($seo['metaDescription'] ?? null) ? $seo['metaDescription'] : null;
        $jsonLd = $normalizer->objectListOrNull($seo['jsonLd'] ?? null);

        return new self(
            (string) $revision->page()->id(),
            $revision->title(),
            $revision->h1(),
            $revision->path(),
            $revision->template(),
            (bool) ($seo['isIndexable'] ?? true),
            $metaDescription,
            \is_string($seo['canonicalUrl'] ?? null) ? $seo['canonicalUrl'] : null,
            \is_string($seo['ogTitle'] ?? null) ? $seo['ogTitle'] : null,
            \is_string($seo['ogDescription'] ?? null) ? $seo['ogDescription'] : $metaDescription,
            \is_string($seo['ogImage'] ?? null) ? $seo['ogImage'] : null,
            \is_string($seo['ogType'] ?? null) ? $seo['ogType'] : null,
            $jsonLd,
            array_map(PageBlockView::fromSnapshot(...), array_values(array_filter(
                $revision->blocksSnapshot(),
                static fn (array $block): bool => (bool) ($block['isEnabled'] ?? true),
            ))),
        );
    }
}

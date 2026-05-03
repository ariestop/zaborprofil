<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Entity\PageRevision;

final readonly class PageRevisionSnapshotBuilder
{
    /**
     * @param list<PageBlock>       $blocks
     * @param array<string, mixed>  $changeSummary
     */
    public function build(Page $page, int $version, array $blocks, ?string $createdBy = null, ?string $comment = null, array $changeSummary = []): PageRevision
    {
        return new PageRevision(
            $page,
            $version,
            $page->title(),
            $page->h1(),
            $page->slug(),
            $page->path(),
            $page->type()->value,
            $page->template(),
            $page->status()->value,
            $this->seoSnapshot($page),
            $this->blocksSnapshot($blocks),
            $this->settingsSnapshot($page),
            $createdBy,
            $comment,
            $changeSummary,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function seoSnapshot(Page $page): array
    {
        return [
            'title' => $page->title(),
            'h1' => $page->h1(),
            'isIndexable' => $page->isIndexable(),
            'metaDescription' => $page->metaDescription(),
            'canonicalUrl' => $page->canonicalUrl(),
            'ogTitle' => $page->ogTitle(),
            'ogDescription' => $page->ogDescription(),
            'ogImage' => $page->ogImage(),
            'ogType' => $page->ogType(),
            'jsonLd' => $page->jsonLd(),
        ];
    }

    /**
     * @param list<PageBlock> $blocks
     *
     * @return list<array<string, mixed>>
     */
    private function blocksSnapshot(array $blocks): array
    {
        return array_map(static fn (PageBlock $block): array => [
            'id' => (string) $block->id(),
            'type' => $block->type()->value,
            'name' => $block->name(),
            'position' => $block->position(),
            'isEnabled' => $block->isEnabled(),
            'visibility' => $block->visibility()->value,
            'content' => $block->content(),
            'settings' => $block->settings(),
        ], $blocks);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsSnapshot(Page $page): array
    {
        return [
            'visibility' => $page->visibility()->value,
            'sortOrder' => $page->sortOrder(),
            'scheduledPublishAt' => $page->scheduledPublishAt()?->format(DATE_ATOM),
            'scheduledUnpublishAt' => $page->scheduledUnpublishAt()?->format(DATE_ATOM),
        ];
    }
}

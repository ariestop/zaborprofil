<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Entity\Page;

final readonly class PublicPageView
{
    /**
     * @param list<PageBlockView> $blocks
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $h1,
        public string $path,
        public string $template,
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
            array_map(PageBlockView::fromBlock(...), $page->enabledBlocks()),
        );
    }
}

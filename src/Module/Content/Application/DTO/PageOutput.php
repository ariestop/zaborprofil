<?php

declare(strict_types=1);

namespace App\Module\Content\Application\DTO;

use App\Module\Content\Domain\Entity\Page;

final readonly class PageOutput
{
    /**
     * @param list<array<string, mixed>>|null $jsonLd
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $slug,
        public string $path,
        public string $h1,
        public string $status,
        public string $template,
        public int $sortOrder,
        public bool $isIndexable,
        public ?string $publishedAt,
        public ?string $metaDescription = null,
        public ?string $canonicalUrl = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public ?string $ogType = null,
        public ?array $jsonLd = null,
    ) {
    }

    public static function fromPage(Page $page): self
    {
        return new self(
            (string) $page->id(),
            $page->type()->value,
            $page->title(),
            $page->slug(),
            $page->path(),
            $page->h1(),
            $page->status()->value,
            $page->template(),
            $page->sortOrder(),
            $page->isIndexable(),
            $page->publishedAt()?->format(DATE_ATOM),
            $page->metaDescription(),
            $page->canonicalUrl(),
            $page->ogTitle(),
            $page->ogDescription(),
            $page->ogImage(),
            $page->ogType(),
            $page->jsonLd(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $this->path,
            'h1' => $this->h1,
            'status' => $this->status,
            'template' => $this->template,
            'sortOrder' => $this->sortOrder,
            'isIndexable' => $this->isIndexable,
            'publishedAt' => $this->publishedAt,
            'seo' => [
                'metaDescription' => $this->metaDescription,
                'canonicalUrl' => $this->canonicalUrl,
                'ogTitle' => $this->ogTitle,
                'ogDescription' => $this->ogDescription,
                'ogImage' => $this->ogImage,
                'ogType' => $this->ogType,
                'jsonLd' => $this->jsonLd,
            ],
        ];
    }
}

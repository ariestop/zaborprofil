<?php

declare(strict_types=1);

namespace App\Module\Content\Application\DTO;

use App\Module\Content\Domain\Entity\PageRevision;

final readonly class PageRevisionOutput
{
    public static function fromRevision(PageRevision $revision): self
    {
        return new self($revision);
    }

    private function __construct(private PageRevision $revision)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->revision->id(),
            'pageId' => (string) $this->revision->page()->id(),
            'version' => $this->revision->version(),
            'title' => $this->revision->title(),
            'h1' => $this->revision->h1(),
            'slug' => $this->revision->slug(),
            'path' => $this->revision->path(),
            'type' => $this->revision->type(),
            'template' => $this->revision->template(),
            'seoSnapshot' => $this->revision->seoSnapshot(),
            'blocksSnapshot' => $this->revision->blocksSnapshot(),
            'settingsSnapshot' => $this->revision->settingsSnapshot(),
            'createdBy' => $this->revision->createdBy(),
            'createdAt' => $this->revision->createdAt()->format(DATE_ATOM),
            'comment' => $this->revision->comment(),
            'changeSummary' => $this->revision->changeSummary(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Content\Application\DTO;

use App\Module\Content\Domain\Entity\PageTemplate;

final readonly class PageTemplateOutput
{
    public static function fromTemplate(PageTemplate $template): self
    {
        return new self($template);
    }

    private function __construct(private PageTemplate $template)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->template->id(),
            'code' => $this->template->code(),
            'name' => $this->template->name(),
            'pageType' => $this->template->pageType()->value,
            'blocksSchema' => $this->template->blocksSchema(),
            'defaultSeo' => $this->template->defaultSeo(),
            'defaultSettings' => $this->template->defaultSettings(),
            'isSystem' => $this->template->isSystem(),
            'isActive' => $this->template->isActive(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Usage;

final readonly class MediaUsageReference
{
    public const string TYPE_PAGE_SEO = 'page_seo';
    public const string TYPE_PAGE_BLOCK = 'page_block';
    public const string TYPE_PRODUCT = 'product';
    public const string TYPE_CATEGORY = 'category';
    public const string TYPE_MENU_ITEM = 'menu_item';
    public const string TYPE_SETTING = 'setting';

    /**
     * @param string      $path      публичный путь файла в `/uploads/media/...`, найденный в источнике
     * @param string      $title     понятное название источника (заголовок страницы, пункт меню)
     * @param string      $location  место внутри источника (поле, блок)
     * @param string|null $adminPath маршрут админки для перехода к источнику
     * @param string|null $status    статус источника (например, `published`)
     */
    public function __construct(
        public string $type,
        public string $sourceId,
        public string $path,
        public string $title,
        public string $location,
        public ?string $adminPath = null,
        public ?string $status = null,
    ) {
    }

    /**
     * @return array{type: string, sourceId: string, title: string, location: string, adminPath: string|null, status: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'sourceId' => $this->sourceId,
            'title' => $this->title,
            'location' => $this->location,
            'adminPath' => $this->adminPath,
            'status' => $this->status,
        ];
    }

    public function key(): string
    {
        return $this->type.'|'.$this->sourceId.'|'.$this->location;
    }
}

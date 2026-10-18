<?php

declare(strict_types=1);

namespace App\Module\Menu\Application\Command;

/**
 * Данные пункта меню из админки. sortOrder = null — поставить в конец среди соседей
 * (при создании) или сохранить текущий порядок (при правке без переноса).
 */
final readonly class MenuItemInput
{
    public function __construct(
        public string $position,
        public string $label,
        public string $url,
        public ?string $hint = null,
        public bool $active = true,
        public ?string $parentId = null,
        public ?int $sortOrder = null,
    ) {
    }
}

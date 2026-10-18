<?php

declare(strict_types=1);

namespace App\Module\Menu\Application\Command;

/**
 * Строка нового порядка меню: пункт и его родитель. Порядок строк в списке — порядок на сайте.
 */
final readonly class MenuOrderEntry
{
    public function __construct(
        public string $id,
        public ?string $parentId,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Menu\Application\Service;

final readonly class BreadcrumbItem
{
    public function __construct(
        public string $label,
        public string $path,
        public bool $current = false,
    ) {
    }

    /**
     * @return array{label: string, path: string, current: bool}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'path' => $this->path,
            'current' => $this->current,
        ];
    }
}

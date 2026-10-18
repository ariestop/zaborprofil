<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Application\Command;

/**
 * Медиа работы из админки: kind — photo | video | embed; для embed src — ссылка на видео VK или Rutube.
 */
final readonly class PortfolioMediaInput
{
    public function __construct(
        public string $kind,
        public string $src,
        public ?string $poster = null,
        public ?string $caption = null,
        public bool $cover = false,
    ) {
    }
}

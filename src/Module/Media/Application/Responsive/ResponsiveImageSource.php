<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Responsive;

final readonly class ResponsiveImageSource
{
    /**
     * @var list<string>
     */
    public const array PRIORITY = ['image/avif', 'image/webp'];

    /**
     * @param array<int, string> $candidates ширина в пикселях => публичный путь, по возрастанию ширины
     */
    public function __construct(
        public string $mimeType,
        public array $candidates,
    ) {
    }

    public function srcset(): string
    {
        $parts = [];
        foreach ($this->candidates as $width => $path) {
            $parts[] = $path.' '.$width.'w';
        }

        return implode(', ', $parts);
    }
}

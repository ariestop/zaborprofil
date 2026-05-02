<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Upload;

final readonly class ValidatedUpload
{
    public function __construct(
        public string $extension,
        public string $mimeType,
        public string $safeFilename,
        public ?int $width,
        public ?int $height,
        public int $size,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

final readonly class RedirectRule
{
    public function __construct(
        public string $sourcePath,
        public string $targetPath,
        public int $statusCode,
    ) {
    }
}

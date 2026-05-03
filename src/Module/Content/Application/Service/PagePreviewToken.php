<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

final readonly class PagePreviewToken
{
    public function __construct(private string $secret)
    {
    }

    public function forPage(string $pageId): string
    {
        return hash_hmac('sha256', $pageId, $this->secret);
    }

    public function isValid(string $pageId, string $token): bool
    {
        return hash_equals($this->forPage($pageId), $token);
    }
}

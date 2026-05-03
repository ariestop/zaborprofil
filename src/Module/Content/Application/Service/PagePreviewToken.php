<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

final readonly class PagePreviewToken
{
    private const int DEFAULT_TTL_SECONDS = 3600;

    public function __construct(private string $secret)
    {
    }

    public function forPage(string $pageId, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): string
    {
        $expiresAt = time() + $ttlSeconds;
        $signature = hash_hmac('sha256', $pageId.'.'.$expiresAt, $this->secret);

        return $expiresAt.'.'.$signature;
    }

    public function isValid(string $pageId, string $token): bool
    {
        [$expiresAt, $signature] = array_pad(explode('.', $token, 2), 2, null);
        if (!\is_string($expiresAt) || !ctype_digit($expiresAt) || !\is_string($signature)) {
            return false;
        }

        if ((int) $expiresAt < time()) {
            return false;
        }

        $expected = hash_hmac('sha256', $pageId.'.'.$expiresAt, $this->secret);

        return hash_equals($expected, $signature);
    }
}

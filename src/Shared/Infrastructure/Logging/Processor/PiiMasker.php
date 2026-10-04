<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging\Processor;

use Throwable;

final readonly class PiiMasker
{
    public static function maskValue(mixed $value): mixed
    {
        if (\is_string($value)) {
            return self::maskString($value);
        }

        if ($value instanceof Throwable || !\is_array($value)) {
            return $value;
        }

        $masked = [];
        foreach ($value as $key => $item) {
            $masked[$key] = self::isSensitiveKey($key) ? '[redacted]' : self::maskValue($item);
        }

        return $masked;
    }

    public static function maskString(string $value): string
    {
        $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $value) ?? $value;
        $value = preg_replace('/(?:\+7|8)\s?\(?\d{3}\)?[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}/', '[phone]', $value) ?? $value;
        $value = preg_replace('/\+\d[\d\s\-()]{8,}\d/', '[phone]', $value) ?? $value;

        return preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[ip]', $value) ?? $value;
    }

    private static function isSensitiveKey(mixed $key): bool
    {
        if (!\is_string($key)) {
            return false;
        }

        return preg_match('/(password|passwd|secret|token|authorization|cookie|phone|email|note_?text)/i', $key) === 1;
    }
}

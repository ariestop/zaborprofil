<?php

declare(strict_types=1);

namespace App\Module\Seo\Domain\Service;

/**
 * Приводит путь к виду, в котором его присылает браузер: не-ASCII символы,
 * пробелы и управляющие байты кодируются как `%XX` (верхний регистр), уже
 * закодированные последовательности сохраняются.
 *
 * Нужен, потому что `Request::getPathInfo()` отдаёт путь в закодированном виде,
 * а редиректы со старых кириллических URL вводятся человеком в читаемом виде.
 */
final class UrlPathEncoder
{
    private const string UNSAFE_BYTES = '/[^\x21-\x7E]|["<>\\\\^`{|}]/';

    private function __construct()
    {
    }

    public static function encode(string $value): string
    {
        $encoded = preg_replace_callback(
            self::UNSAFE_BYTES,
            static fn (array $match): string => rawurlencode($match[0]),
            $value,
        );
        $encoded = \is_string($encoded) ? $encoded : $value;

        $normalized = preg_replace_callback(
            '/%[0-9a-fA-F]{2}/',
            static fn (array $match): string => strtoupper($match[0]),
            $encoded,
        );

        return \is_string($normalized) ? $normalized : $encoded;
    }
}

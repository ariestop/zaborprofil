<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Entity;

use InvalidArgumentException;

/**
 * Проверки текстовых полей портфолио: обрезка пробелов, длина, адрес (slug).
 */
final class PortfolioText
{
    public static function required(string $value, int $max, string $message): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        return self::limit($value, $max);
    }

    public static function optional(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : self::limit($value, $max);
    }

    public static function slug(string $slug): string
    {
        $slug = strtolower(trim($slug, " \t\n\r\0\x0B/"));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || \strlen($slug) > 120) {
            throw new InvalidArgumentException('Адрес — латиница, цифры и дефисы, до 120 символов (например, zabor-zhalyuzi).');
        }

        return $slug;
    }

    private static function limit(string $value, int $max): string
    {
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(\sprintf('Текст длиннее %d символов.', $max));
        }

        return $value;
    }
}

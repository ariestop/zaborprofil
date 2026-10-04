<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\ValueObject;

final readonly class PhoneNumber
{
    /**
     * Цифры телефона в едином виде для поиска: ведущая «8» у 11-значного номера заменяется на «7».
     */
    public static function digits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (\strlen($digits) === 11 && $digits[0] === '8') {
            return '7'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Маска для логов и аудита: остаются первая цифра и две последние, остальное скрыто.
     */
    public static function mask(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $length = \strlen($digits);

        if ($length <= 4) {
            return '***';
        }

        return $digits[0].str_repeat('*', $length - 3).substr($digits, -2);
    }
}

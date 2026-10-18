<?php

declare(strict_types=1);

namespace App\Shared\UI\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Склонение слова после числа: `{{ n }} {{ n|plural_ru('работа', 'работы', 'работ') }}`.
 */
final class RussianPluralExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('plural_ru', self::plural(...))];
    }

    public static function plural(int $count, string $one, string $few, string $many): string
    {
        $mod10 = abs($count) % 10;
        $mod100 = abs($count) % 100;

        return match (true) {
            $mod10 === 1 && $mod100 !== 11 => $one,
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => $few,
            default => $many,
        };
    }
}

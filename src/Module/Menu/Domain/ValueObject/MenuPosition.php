<?php

declare(strict_types=1);

namespace App\Module\Menu\Domain\ValueObject;

use InvalidArgumentException;

final readonly class MenuPosition
{
    public const string HEADER = 'header';
    public const string FOOTER = 'footer';
    public const string SERVICE = 'service';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::HEADER,
            self::FOOTER,
            self::SERVICE,
        ];
    }

    public static function normalize(string $position): string
    {
        $normalized = strtolower(trim($position));
        if (!\in_array($normalized, self::values(), true)) {
            throw new InvalidArgumentException('Menu position must be one of: '.implode(', ', self::values()).'.');
        }

        return $normalized;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::HEADER, 'label' => 'Header'],
            ['value' => self::FOOTER, 'label' => 'Footer'],
            ['value' => self::SERVICE, 'label' => 'Service navigation'],
        ];
    }
}

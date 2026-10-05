<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\ValueObject;

/**
 * UTM-метки заявки. Из присланных данных остаются только известные ключи со строковыми значениями ограниченной длины.
 */
final readonly class LeadUtm
{
    public const int VALUE_MAX_LENGTH = 120;

    /**
     * @var list<string>
     */
    public const array KEYS = ['source', 'medium', 'campaign', 'term', 'content'];

    /**
     * @return array<string, string>
     */
    public static function sanitize(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $result = [];
        foreach (self::KEYS as $key) {
            $item = $value[$key] ?? null;
            if (!\is_string($item)) {
                continue;
            }

            $item = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $item));
            if ($item !== '') {
                $result[$key] = mb_substr($item, 0, self::VALUE_MAX_LENGTH);
            }
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

final readonly class SnapshotValueNormalizer
{
    public function string(mixed $value, string $default = ''): string
    {
        return \is_string($value) ? $value : $default;
    }

    public function int(mixed $value, int $default = 0): int
    {
        return \is_int($value) ? $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function stringKeyedArray(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function objectListOrNull(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        $items = [];
        foreach ($value as $item) {
            if (\is_array($item)) {
                $items[] = $this->stringKeyedArray($item);
            }
        }

        return $items === [] ? null : $items;
    }
}

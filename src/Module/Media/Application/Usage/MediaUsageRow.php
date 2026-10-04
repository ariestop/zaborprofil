<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Usage;

use Symfony\Component\Uid\Ulid;

/**
 * Типобезопасная обёртка над строкой DBAL-результата для провайдеров использования медиа.
 */
final readonly class MediaUsageRow
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data)
    {
    }

    public function text(string $column): string
    {
        return $this->nullableText($column) ?? '';
    }

    public function nullableText(string $column): ?string
    {
        $value = $this->data[$column] ?? null;

        return \is_string($value) ? $value : (\is_int($value) || \is_float($value) ? (string) $value : null);
    }

    public function flag(string $column): bool
    {
        $value = $this->data[$column] ?? null;

        return $value === true || $value === 1 || $value === '1';
    }

    public function ulid(string $column): string
    {
        $value = $this->data[$column] ?? null;

        return \is_string($value) && \strlen($value) === 16 ? Ulid::fromBinary($value)->toBase32() : '';
    }
}

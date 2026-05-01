<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

final class JsonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);

        if (!\is_array($decoded)) {
            throw new InvalidArgumentException('Request body must be a JSON object.');
        }

        return $this->stringKeyedArray($decoded, 'Request body must be a JSON object.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function string(array $payload, string $key, ?string $default = null): string
    {
        $value = $payload[$key] ?? $default;

        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function nullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if ($value !== null && !\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a string or null.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function int(array $payload, string $key, int $default = 0): int
    {
        $value = $payload[$key] ?? $default;

        if (!\is_int($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be an integer.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function bool(array $payload, string $key, bool $default = true): bool
    {
        $value = $payload[$key] ?? $default;

        if (!\is_bool($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a boolean.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function object(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        if (!\is_array($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be an object.', $key));
        }

        return $this->stringKeyedArray($value, \sprintf('Field "%s" must be an object.', $key));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    public function stringList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        if (!\is_array($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be an array.', $key));
        }

        $items = [];
        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw new InvalidArgumentException(\sprintf('Field "%s" must contain only strings.', $key));
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<mixed, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $value, string $message): array
    {
        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException($message);
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Admin;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Типизированный доступ к JSON-телу запроса админ-API. Ошибки типов превращаются в 422 через AdminApiErrorResponder.
 */
final readonly class JsonPayload
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $decoded = json_decode($request->getContent(), true);
        if (!\is_array($decoded)) {
            throw new InvalidArgumentException('Request body must be a JSON object.');
        }

        $data = [];
        foreach ($decoded as $key => $value) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException('Request body must be a JSON object.');
            }

            $data[$key] = $value;
        }

        return new self($data);
    }

    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;
        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a string.', $key));
        }

        return $value;
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;
        if ($value !== null && !\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a string or null.', $key));
        }

        return $value;
    }

    public function int(string $key, int $default): int
    {
        $value = $this->data[$key] ?? $default;
        if (!\is_int($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be an integer.', $key));
        }

        return $value;
    }

    public function bool(string $key, bool $default): bool
    {
        $value = $this->data[$key] ?? $default;
        if (!\is_bool($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a boolean.', $key));
        }

        return $value;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }
}

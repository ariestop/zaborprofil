<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\ClientError;

use InvalidArgumentException;

final readonly class ClientErrorReport
{
    public const array SOURCES = ['error-boundary', 'window-error', 'unhandled-rejection', 'api'];

    private const int MAX_MESSAGE = 500;
    private const int MAX_STACK = 4000;
    private const int MAX_PATH = 300;

    public function __construct(
        public string $source,
        public string $message,
        public string $path,
        public ?string $stack,
        public ?string $componentStack,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $message = self::requiredString($payload, 'message');
        $source = self::optionalString($payload, 'source') ?? 'window-error';
        if (!\in_array($source, self::SOURCES, true)) {
            throw new InvalidArgumentException('Field "source" has an unsupported value.');
        }

        return new self(
            $source,
            self::truncate(self::singleLine($message), self::MAX_MESSAGE),
            self::path(self::optionalString($payload, 'url') ?? ''),
            self::truncateNullable(self::optionalString($payload, 'stack'), self::MAX_STACK),
            self::truncateNullable(self::optionalString($payload, 'componentStack'), self::MAX_STACK),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function requiredString(array $payload, string $key): string
    {
        $value = self::optionalString($payload, $key);
        if ($value === null || trim($value) === '') {
            throw new InvalidArgumentException(\sprintf('Field "%s" is required.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be a string.', $key));
        }

        return $value;
    }

    private static function singleLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private static function path(string $url): string
    {
        $path = parse_url($url, \PHP_URL_PATH);

        return self::truncate(\is_string($path) ? $path : '', self::MAX_PATH);
    }

    private static function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }

    private static function truncateNullable(?string $value, int $length): ?string
    {
        return $value === null || $value === '' ? null : self::truncate($value, $length);
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use DateTimeImmutable;

final readonly class HealthCheckResult
{
    public function __construct(
        public string $name,
        public string $label,
        public string $status,
        public string $message,
        /**
         * @var array<string, scalar|null>
         */
        public array $details = [],
        public ?DateTimeImmutable $checkedAt = null,
    ) {
    }

    /**
     * @param array<string, scalar|null> $details
     */
    public static function ok(string $name, string $label, string $message = 'OK', array $details = []): self
    {
        return new self($name, $label, 'ok', $message, $details, new DateTimeImmutable());
    }

    /**
     * @param array<string, scalar|null> $details
     */
    public static function warning(string $name, string $label, string $message, array $details = []): self
    {
        return new self($name, $label, 'warning', $message, $details, new DateTimeImmutable());
    }

    /**
     * @param array<string, scalar|null> $details
     */
    public static function fail(string $name, string $label, string $message, array $details = []): self
    {
        return new self($name, $label, 'fail', $message, $details, new DateTimeImmutable());
    }

    public function isHealthy(): bool
    {
        return $this->status !== 'fail';
    }

    /**
     * @return array{name: string, label: string, status: string, message: string, details: array<string, scalar|null>, checkedAt: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'status' => $this->status,
            'message' => $this->message,
            'details' => $this->details,
            'checkedAt' => ($this->checkedAt ?? new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}

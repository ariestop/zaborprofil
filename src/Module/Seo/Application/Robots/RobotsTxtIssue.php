<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Robots;

final readonly class RobotsTxtIssue
{
    public const string ERROR = 'error';
    public const string WARNING = 'warning';

    public function __construct(
        public string $severity,
        public ?int $line,
        public string $message,
    ) {
    }

    public function isError(): bool
    {
        return $this->severity === self::ERROR;
    }

    /**
     * @return array{severity: string, line: int|null, message: string}
     */
    public function toArray(): array
    {
        return ['severity' => $this->severity, 'line' => $this->line, 'message' => $this->message];
    }
}

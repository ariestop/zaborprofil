<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\ValueObject;

use InvalidArgumentException;

final readonly class LeadStatus
{
    public const string NEW = 'new';
    public const string IN_PROGRESS = 'in_progress';
    public const string DONE = 'done';
    public const string SPAM = 'spam';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::NEW,
            self::IN_PROGRESS,
            self::DONE,
            self::SPAM,
        ];
    }

    public static function normalize(string $status): string
    {
        $normalized = strtolower(trim($status));
        if (!\in_array($normalized, self::values(), true)) {
            throw new InvalidArgumentException('Lead status is not allowed.');
        }

        return $normalized;
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Journal;

/**
 * Событие жизненного цикла страницы для журнала. Актор `null` означает системное действие (планировщик).
 */
final readonly class PageWorkflowEvent
{
    public const string STATUS_CHANGED = 'status_changed';
    public const string PUBLISHED = 'published';
    public const string UNPUBLISHED = 'unpublished';
    public const string SCHEDULED = 'scheduled';
    public const string SCHEDULE_CANCELLED = 'schedule_cancelled';
    public const string SCHEDULED_PUBLISHED = 'scheduled_published';
    public const string SCHEDULED_UNPUBLISHED = 'scheduled_unpublished';
    public const string SCHEDULE_FAILED = 'schedule_failed';
    public const string ROLLED_BACK = 'rolled_back';
    public const string ARCHIVED = 'archived';

    /**
     * @var list<string>
     */
    public const array ALL = [
        self::STATUS_CHANGED,
        self::PUBLISHED,
        self::UNPUBLISHED,
        self::SCHEDULED,
        self::SCHEDULE_CANCELLED,
        self::SCHEDULED_PUBLISHED,
        self::SCHEDULED_UNPUBLISHED,
        self::SCHEDULE_FAILED,
        self::ROLLED_BACK,
        self::ARCHIVED,
    ];

    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public string $name,
        public string $pageId,
        public string $path,
        public ?string $fromStatus,
        public ?string $toStatus,
        public ?string $comment = null,
        public array $details = [],
    ) {
    }
}

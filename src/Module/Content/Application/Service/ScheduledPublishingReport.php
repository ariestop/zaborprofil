<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

final class ScheduledPublishingReport
{
    public const string OUTCOME_PUBLISHED = 'published';
    public const string OUTCOME_UNPUBLISHED = 'unpublished';
    public const string OUTCOME_FAILED = 'failed';
    public const string OUTCOME_ERROR = 'error';
    public const string OUTCOME_WOULD_PUBLISH = 'would_publish';
    public const string OUTCOME_WOULD_UNPUBLISH = 'would_unpublish';

    /**
     * @var list<array{pageId: string, path: string, outcome: string, message: string|null}>
     */
    private array $items = [];

    public function add(string $pageId, string $path, string $outcome, ?string $message = null): void
    {
        $this->items[] = ['pageId' => $pageId, 'path' => $path, 'outcome' => $outcome, 'message' => $message];
    }

    /**
     * @return list<array{pageId: string, path: string, outcome: string, message: string|null}>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function count(string $outcome): int
    {
        return \count(array_filter($this->items, static fn (array $item): bool => $item['outcome'] === $outcome));
    }

    /**
     * Ошибки, требующие внимания: сбой чек-листа публикации или непредвиденное исключение.
     */
    public function hasProblems(): bool
    {
        return $this->count(self::OUTCOME_FAILED) > 0 || $this->count(self::OUTCOME_ERROR) > 0;
    }
}

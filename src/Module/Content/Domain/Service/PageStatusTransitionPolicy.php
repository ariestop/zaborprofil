<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Service;

use App\Module\Content\Domain\Enum\PageStatus;
use InvalidArgumentException;

final readonly class PageStatusTransitionPolicy
{
    /**
     * @var array<string, list<PageStatus>>
     */
    private const array TRANSITIONS = [
        'draft' => [PageStatus::Review, PageStatus::Approved, PageStatus::Published, PageStatus::Deleted],
        'review' => [PageStatus::Approved, PageStatus::Draft, PageStatus::Deleted],
        'approved' => [PageStatus::Published, PageStatus::Scheduled, PageStatus::Draft, PageStatus::Deleted],
        'published' => [PageStatus::Unpublished, PageStatus::Scheduled, PageStatus::Archived, PageStatus::Deleted],
        'scheduled' => [PageStatus::Published, PageStatus::Draft, PageStatus::Deleted],
        'unpublished' => [PageStatus::Draft, PageStatus::Published, PageStatus::Archived, PageStatus::Deleted],
        'archived' => [PageStatus::Draft, PageStatus::Deleted],
        'deleted' => [PageStatus::Draft],
    ];

    /**
     * @param list<string> $roles
     */
    public function assertAllowed(PageStatus $from, PageStatus $to, array $roles): void
    {
        if ($from === $to) {
            return;
        }

        if (!\in_array($to, self::TRANSITIONS[$from->value], true)) {
            throw new InvalidArgumentException(\sprintf('Page status cannot transition from "%s" to "%s".', $from->value, $to->value));
        }

        if (!$this->roleCanSet($to, $roles, $from)) {
            throw new InvalidArgumentException(\sprintf('Current role cannot set page status "%s".', $to->value));
        }
    }

    /**
     * @return list<string>
     */
    public function nextStatusValues(PageStatus $from): array
    {
        return array_map(static fn (PageStatus $status): string => $status->value, self::TRANSITIONS[$from->value]);
    }

    /**
     * @param list<string> $roles
     */
    private function roleCanSet(PageStatus $status, array $roles, PageStatus $from): bool
    {
        if ($this->hasAnyRole($roles, ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'])) {
            return true;
        }

        return match ($status) {
            PageStatus::Draft => $from !== PageStatus::Deleted && $this->hasAnyRole($roles, ['ROLE_EDITOR', 'ROLE_SEO']),
            PageStatus::Review => $this->hasAnyRole($roles, ['ROLE_EDITOR', 'ROLE_SEO']),
            PageStatus::Approved => $this->hasAnyRole($roles, ['ROLE_SEO']),
            default => false,
        };
    }

    /**
     * @param list<string> $roles
     * @param list<string> $allowed
     */
    private function hasAnyRole(array $roles, array $allowed): bool
    {
        return array_any($allowed, static fn (string $role): bool => \in_array($role, $roles, true));
    }
}

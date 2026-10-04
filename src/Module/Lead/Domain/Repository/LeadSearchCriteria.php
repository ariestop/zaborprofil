<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\Repository;

use DateTimeImmutable;

final readonly class LeadSearchCriteria
{
    public const string SORT_CREATED = 'createdAt';
    public const string SORT_UPDATED = 'updatedAt';
    public const string SORT_NAME = 'name';
    public const string SORT_STATUS = 'status';
    public const string SORT_SOURCE = 'source';

    /**
     * @var list<string>
     */
    public const array SORTS = [self::SORT_CREATED, self::SORT_UPDATED, self::SORT_NAME, self::SORT_STATUS, self::SORT_SOURCE];

    public const string ASSIGNEE_NONE = 'none';

    /**
     * @param ?string $assignee идентификатор пользователя или {@see self::ASSIGNEE_NONE} для заявок без ответственного
     * @param ?DateTimeImmutable $createdFrom включительно
     * @param ?DateTimeImmutable $createdBefore исключительно
     */
    public function __construct(
        public ?string $query = null,
        public ?string $status = null,
        public ?string $source = null,
        public ?DateTimeImmutable $createdFrom = null,
        public ?DateTimeImmutable $createdBefore = null,
        public ?string $assignee = null,
        public string $sort = self::SORT_CREATED,
        public bool $descending = true,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }

    public function withoutStatus(): self
    {
        return new self(
            $this->query,
            null,
            $this->source,
            $this->createdFrom,
            $this->createdBefore,
            $this->assignee,
            $this->sort,
            $this->descending,
            $this->page,
            $this->perPage,
        );
    }
}

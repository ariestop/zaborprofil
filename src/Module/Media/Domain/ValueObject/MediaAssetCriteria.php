<?php

declare(strict_types=1);

namespace App\Module\Media\Domain\ValueObject;

use InvalidArgumentException;

final readonly class MediaAssetCriteria
{
    public const string TYPE_IMAGE = 'image';
    public const string TYPE_DOCUMENT = 'document';

    public const string SORT_NEWEST = 'newest';
    public const string SORT_OLDEST = 'oldest';
    public const string SORT_NAME = 'name';
    public const string SORT_SIZE = 'size';

    public const int DEFAULT_PER_PAGE = 24;
    public const int MAX_PER_PAGE = 100;
    public const int MAX_SEARCH_LENGTH = 100;

    public string $search;

    public function __construct(
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
        string $search = '',
        public ?string $type = null,
        public string $sort = self::SORT_NEWEST,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be a positive integer.');
        }

        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException(\sprintf('Per page must be between 1 and %d.', self::MAX_PER_PAGE));
        }

        if ($type !== null && !\in_array($type, [self::TYPE_IMAGE, self::TYPE_DOCUMENT], true)) {
            throw new InvalidArgumentException('Unknown media type filter.');
        }

        if (!\in_array($sort, [self::SORT_NEWEST, self::SORT_OLDEST, self::SORT_NAME, self::SORT_SIZE], true)) {
            throw new InvalidArgumentException('Unknown media sort order.');
        }

        $search = trim($search);
        if (mb_strlen($search) > self::MAX_SEARCH_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Search query cannot be longer than %d characters.', self::MAX_SEARCH_LENGTH));
        }

        $this->search = $search;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}

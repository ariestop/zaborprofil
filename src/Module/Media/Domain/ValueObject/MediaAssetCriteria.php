<?php

declare(strict_types=1);

namespace App\Module\Media\Domain\ValueObject;

use App\Module\Media\Domain\Entity\MediaAsset;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MediaAssetCriteria
{
    public const string TYPE_IMAGE = 'image';
    public const string TYPE_DOCUMENT = 'document';

    public const string SORT_NEWEST = 'newest';
    public const string SORT_OLDEST = 'oldest';
    public const string SORT_NAME = 'name';
    public const string SORT_SIZE = 'size';
    public const string SORT_SIZE_ASC = 'size_asc';

    public const string USAGE_USED = 'used';
    public const string USAGE_UNUSED = 'unused';

    /**
     * @var array<string, list<string>>
     */
    public const array FORMAT_MIME_TYPES = [
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif'],
        'pdf' => ['application/pdf', 'application/x-pdf'],
    ];

    public const int DEFAULT_PER_PAGE = 24;
    public const int MAX_PER_PAGE = 100;
    public const int MAX_SEARCH_LENGTH = 100;

    public string $search;

    public ?string $folder;

    /**
     * @param list<string> $usedAssetIds идентификаторы ассетов, найденных в контенте; нужны только при фильтре по использованию
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
        string $search = '',
        public ?string $type = null,
        public string $sort = self::SORT_NEWEST,
        ?string $folder = null,
        public ?string $format = null,
        public ?string $usage = null,
        public array $usedAssetIds = [],
        public ?DateTimeImmutable $createdFrom = null,
        public ?DateTimeImmutable $createdTo = null,
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

        if (!\in_array($sort, [self::SORT_NEWEST, self::SORT_OLDEST, self::SORT_NAME, self::SORT_SIZE, self::SORT_SIZE_ASC], true)) {
            throw new InvalidArgumentException('Unknown media sort order.');
        }

        if ($format !== null && !\array_key_exists($format, self::FORMAT_MIME_TYPES)) {
            throw new InvalidArgumentException('Unknown media format filter.');
        }

        if ($usage !== null && !\in_array($usage, [self::USAGE_USED, self::USAGE_UNUSED], true)) {
            throw new InvalidArgumentException('Unknown media usage filter.');
        }

        if ($createdFrom !== null && $createdTo !== null && $createdFrom > $createdTo) {
            throw new InvalidArgumentException('Upload date range is invalid.');
        }

        $search = trim($search);
        if (mb_strlen($search) > self::MAX_SEARCH_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Search query cannot be longer than %d characters.', self::MAX_SEARCH_LENGTH));
        }

        $folder = $folder === null ? null : trim($folder);
        if ($folder !== null && mb_strlen($folder) > MediaAsset::FOLDER_MAX_LENGTH) {
            throw new InvalidArgumentException('Folder filter is too long.');
        }

        $this->search = $search;
        $this->folder = $folder === '' ? null : $folder;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}

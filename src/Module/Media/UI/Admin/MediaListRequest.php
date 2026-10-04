<?php

declare(strict_types=1);

namespace App\Module\Media\UI\Admin;

use App\Module\Media\Domain\ValueObject\MediaAssetCriteria;
use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

final class MediaListRequest
{
    /**
     * @param Closure(): list<string>|null $usedAssetIds ленивый источник id используемых ассетов; вызывается только при фильтре `usage`
     */
    public static function criteriaFrom(Request $request, ?Closure $usedAssetIds = null): MediaAssetCriteria
    {
        $type = self::optionalString($request, 'type');
        $sort = self::optionalString($request, 'sort');
        $format = self::optionalString($request, 'format');
        $usage = self::optionalString($request, 'usage');
        $usage = $usage === '' ? null : $usage;

        $usedIds = [];
        if ($usage !== null && $usedAssetIds !== null) {
            $usedIds = $usedAssetIds();
        }

        return new MediaAssetCriteria(
            self::integer($request, 'page', 1),
            self::integer($request, 'perPage', MediaAssetCriteria::DEFAULT_PER_PAGE),
            self::optionalString($request, 'q') ?? '',
            $type === '' ? null : $type,
            $sort === null || $sort === '' ? MediaAssetCriteria::SORT_NEWEST : $sort,
            self::optionalString($request, 'folder'),
            $format === '' ? null : $format,
            $usage,
            $usedIds,
            self::date($request, 'from', false),
            self::date($request, 'to', true),
        );
    }

    private static function integer(Request $request, string $key, int $default): int
    {
        $value = $request->query->get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        if (!ctype_digit((string) $value)) {
            throw new InvalidArgumentException(\sprintf('Query parameter "%s" must be a positive integer.', $key));
        }

        return (int) $value;
    }

    private static function optionalString(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private static function date(Request $request, string $key, bool $endOfDay): ?DateTimeImmutable
    {
        $value = self::optionalString($request, $key);
        if ($value === null || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(\sprintf('Query parameter "%s" must be a date in YYYY-MM-DD format.', $key));
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date;
    }
}

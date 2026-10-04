<?php

declare(strict_types=1);

namespace App\Module\Media\UI\Admin;

use App\Module\Media\Domain\ValueObject\MediaAssetCriteria;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

final class MediaListRequest
{
    public static function criteriaFrom(Request $request): MediaAssetCriteria
    {
        $type = self::optionalString($request, 'type');
        $sort = self::optionalString($request, 'sort');

        return new MediaAssetCriteria(
            self::integer($request, 'page', 1),
            self::integer($request, 'perPage', MediaAssetCriteria::DEFAULT_PER_PAGE),
            self::optionalString($request, 'q') ?? '',
            $type === '' ? null : $type,
            $sort === null || $sort === '' ? MediaAssetCriteria::SORT_NEWEST : $sort,
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
}

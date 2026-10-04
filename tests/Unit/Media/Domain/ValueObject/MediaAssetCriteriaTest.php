<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\ValueObject;

use App\Module\Media\Domain\ValueObject\MediaAssetCriteria;
use App\Module\Media\Domain\ValueObject\MediaAssetPage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaAssetCriteriaTest extends TestCase
{
    public function testDefaultsAndOffset(): void
    {
        $criteria = new MediaAssetCriteria(3, 20, '  забор ');

        self::assertSame('забор', $criteria->search);
        self::assertSame(40, $criteria->offset());
        self::assertSame(MediaAssetCriteria::SORT_NEWEST, $criteria->sort);
        self::assertNull($criteria->type);
    }

    /**
     * @return iterable<string, array{0: callable(): MediaAssetCriteria}>
     */
    public static function invalidArguments(): iterable
    {
        yield 'page zero' => [static fn (): MediaAssetCriteria => new MediaAssetCriteria(page: 0)];
        yield 'per page zero' => [static fn (): MediaAssetCriteria => new MediaAssetCriteria(perPage: 0)];
        yield 'per page too large' => [static fn (): MediaAssetCriteria => new MediaAssetCriteria(perPage: 101)];
        yield 'unknown type' => [static fn (): MediaAssetCriteria => new MediaAssetCriteria(type: 'video')];
        yield 'unknown sort' => [static fn (): MediaAssetCriteria => new MediaAssetCriteria(sort: 'random')];
        yield 'long search' => [static fn (): MediaAssetCriteria => new MediaAssetCriteria(search: str_repeat('a', 101))];
    }

    /**
     * @param callable(): MediaAssetCriteria $factory
     */
    #[DataProvider('invalidArguments')]
    public function testRejectsInvalidArguments(callable $factory): void
    {
        $this->expectException(InvalidArgumentException::class);

        $factory();
    }

    public function testPageCalculatesTotalPages(): void
    {
        self::assertSame(1, (new MediaAssetPage([], 0, 1, 24))->totalPages());
        self::assertSame(3, (new MediaAssetPage([], 49, 1, 24))->totalPages());
    }
}

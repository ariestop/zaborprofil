<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\UI\Twig;

use App\Shared\UI\Twig\RussianPluralExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RussianPluralExtensionTest extends TestCase
{
    #[DataProvider('counts')]
    public function testPicksTheRightForm(int $count, string $expected): void
    {
        self::assertSame($expected, RussianPluralExtension::plural($count, 'работа', 'работы', 'работ'));
    }

    /**
     * @return iterable<string, array{0: int, 1: string}>
     */
    public static function counts(): iterable
    {
        yield '0' => [0, 'работ'];
        yield '1' => [1, 'работа'];
        yield '2' => [2, 'работы'];
        yield '5' => [5, 'работ'];
        yield '11' => [11, 'работ'];
        yield '12' => [12, 'работ'];
        yield '21' => [21, 'работа'];
        yield '24' => [24, 'работы'];
        yield '111' => [111, 'работ'];
        yield '122' => [122, 'работы'];
    }
}

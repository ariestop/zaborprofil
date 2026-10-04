<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Application\Service\Diff\TextDiffer;
use PHPUnit\Framework\TestCase;

final class TextDifferTest extends TestCase
{
    public function testIdenticalTextIsSingleEqualSegment(): void
    {
        self::assertSame([['op' => 'equal', 'text' => 'Забор из профнастила']], (new TextDiffer())->diff('Забор из профнастила', 'Забор из профнастила'));
    }

    public function testReplacedWordProducesDeleteAndInsertAroundEqualParts(): void
    {
        $segments = (new TextDiffer())->diff('Забор из профнастила под ключ', 'Забор из штакетника под ключ');

        self::assertSame(
            [
                ['op' => 'equal', 'text' => 'Забор из '],
                ['op' => 'delete', 'text' => 'профнастила'],
                ['op' => 'insert', 'text' => 'штакетника'],
                ['op' => 'equal', 'text' => ' под ключ'],
            ],
            $segments,
        );
    }

    public function testEmptyBeforeIsSingleInsert(): void
    {
        self::assertSame([['op' => 'insert', 'text' => 'Новый текст']], (new TextDiffer())->diff('', 'Новый текст'));
    }

    public function testVeryLargeInputFallsBackToWholeReplacement(): void
    {
        $before = implode(' ', array_map(static fn (int $i): string => 'a'.$i, range(1, 800)));
        $after = implode(' ', array_map(static fn (int $i): string => 'b'.$i, range(1, 800)));

        self::assertSame(
            [['op' => 'delete', 'text' => $before], ['op' => 'insert', 'text' => $after]],
            (new TextDiffer())->diff($before, $after),
        );
    }
}

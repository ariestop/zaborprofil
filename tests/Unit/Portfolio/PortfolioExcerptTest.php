<?php

declare(strict_types=1);

namespace App\Tests\Unit\Portfolio;

use App\Module\Portfolio\UI\Twig\PortfolioTwigExtension;
use PHPUnit\Framework\TestCase;

final class PortfolioExcerptTest extends TestCase
{
    public function testShortTextStaysWholeWithoutTags(): void
    {
        self::assertSame('Забор 42 м & калитка', PortfolioTwigExtension::excerpt("<p>Забор  42&nbsp;м &amp;\n калитка</p>"));
        self::assertSame('', PortfolioTwigExtension::excerpt(null));
    }

    public function testLongTextIsCutOnAWordWithEllipsis(): void
    {
        $text = 'Поставили забор из жалюзи вдоль улицы и двора, сделали откатные ворота с автоматикой и калитку';

        self::assertSame('Поставили забор из жалюзи вдоль улицы и двора…', PortfolioTwigExtension::excerpt($text, 50));
    }

    public function testCyrillicBeforeADashIsNotBroken(): void
    {
        // «р» в UTF-8 кончается байтом 0x80, как и «—»: побайтовая обрезка испортила бы букву.
        $excerpt = PortfolioTwigExtension::excerpt('Забор — профнастил, монтаж за 3 дня, гарантия', 8);

        self::assertSame('Забор…', $excerpt);
        self::assertTrue(mb_check_encoding($excerpt, 'UTF-8'));
        self::assertSame('Монтажер…', PortfolioTwigExtension::excerpt('Монтажер — опытный мастер', 9));
    }
}

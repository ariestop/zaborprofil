<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Enum\PageType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    public function testPageStartsAsDraftAndCanBePublished(): void
    {
        $page = new Page(PageType::Landing, 'Заборы', 'zabory', '/zabory/', 'Заборы');

        self::assertSame(PageStatus::Draft, $page->status());

        $page->publish();

        self::assertSame(PageStatus::Published, $page->status());
        self::assertNotNull($page->publishedAt());
    }

    public function testInvalidSlugIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Page(PageType::Landing, 'Заборы', 'Заборы', '/zabory/', 'Заборы');
    }

    public function testEnabledBlocksAreReturnedInPositionOrder(): void
    {
        $page = new Page(PageType::Landing, 'Заборы', 'zabory', '/zabory/', 'Заборы');
        new PageBlock($page, BlockType::Hero, 'Hero', 0, ['title' => 'Hero']);
        new PageBlock($page, BlockType::Text, 'Text', 1, ['text' => 'Hidden'], enabled: false);

        self::assertCount(2, $page->blocks());
        self::assertCount(1, $page->enabledBlocks());
        self::assertSame('Hero', $page->enabledBlocks()[0]->name());
    }
}

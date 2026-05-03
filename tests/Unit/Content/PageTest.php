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

    public function testSeoMetadataDefaultsAreNull(): void
    {
        $page = new Page(PageType::Landing, 'Заборы', 'zabory', '/zabory/', 'Заборы');

        self::assertNull($page->metaDescription());
        self::assertNull($page->canonicalUrl());
        self::assertNull($page->ogTitle());
        self::assertNull($page->ogDescription());
        self::assertNull($page->ogImage());
        self::assertNull($page->ogType());
        self::assertNull($page->jsonLd());
    }

    public function testUpdateSeoMetadataTrimsAndPersists(): void
    {
        $page = new Page(PageType::Landing, 'Заборы', 'zabory', '/zabory/', 'Заборы');

        $page->updateSeoMetadata(
            metaDescription: '  Купить заборы в Москве — лучшие цены  ',
            canonicalUrl: 'https://zaborprofil.ru/zabory/',
            ogTitle: 'OG Title',
            ogDescription: 'OG Desc',
            ogImage: 'https://zaborprofil.ru/og/zabory.jpg',
            ogType: 'website',
            jsonLd: [['@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'Забор']],
        );

        self::assertSame('Купить заборы в Москве — лучшие цены', $page->metaDescription());
        self::assertSame('https://zaborprofil.ru/zabory/', $page->canonicalUrl());
        self::assertSame('OG Title', $page->ogTitle());
        self::assertSame('https://zaborprofil.ru/og/zabory.jpg', $page->ogImage());
        self::assertSame('website', $page->ogType());
        self::assertCount(1, (array) $page->jsonLd());
    }

    public function testUpdateSeoMetadataRejectsTooLongDescription(): void
    {
        $page = new Page(PageType::Landing, 'Заборы', 'zabory', '/zabory/', 'Заборы');

        $this->expectException(InvalidArgumentException::class);

        $page->updateSeoMetadata(
            metaDescription: str_repeat('x', 321),
            canonicalUrl: null,
            ogTitle: null,
            ogDescription: null,
            ogImage: null,
            ogType: null,
            jsonLd: null,
        );
    }

    public function testUpdateSeoMetadataRejectsRelativeCanonical(): void
    {
        $page = new Page(PageType::Landing, 'Заборы', 'zabory', '/zabory/', 'Заборы');

        $this->expectException(InvalidArgumentException::class);

        $page->updateSeoMetadata(
            metaDescription: null,
            canonicalUrl: '/zabory',
            ogTitle: null,
            ogDescription: null,
            ogImage: null,
            ogType: null,
            jsonLd: null,
        );
    }

    public function testUpdateSeoMetadataRejectsJsonLdWithoutContext(): void
    {
        $page = new Page(PageType::Landing, 'Заборы', 'zabory', '/zabory/', 'Заборы');

        $this->expectException(InvalidArgumentException::class);

        $page->updateSeoMetadata(
            metaDescription: null,
            canonicalUrl: null,
            ogTitle: null,
            ogDescription: null,
            ogImage: null,
            ogType: null,
            jsonLd: [['@type' => 'Product']],
        );
    }
}

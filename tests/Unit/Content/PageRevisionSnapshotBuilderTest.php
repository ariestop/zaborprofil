<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Application\Service\PageRevisionSnapshotBuilder;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Enum\PageType;
use PHPUnit\Framework\TestCase;

final class PageRevisionSnapshotBuilderTest extends TestCase
{
    public function testBuildsImmutableSnapshotFromPageAndBlocks(): void
    {
        $page = new Page(PageType::Service, 'Монтаж заборов', 'montazh-zaborov', '/montazh-zaborov/', 'Монтаж заборов');
        $page->updateSeoMetadata(
            'Монтаж заборов под ключ в Москве и области.',
            null,
            'Монтаж заборов',
            null,
            null,
            'website',
            [['@context' => 'https://schema.org', '@type' => 'Service']],
        );
        $block = new PageBlock($page, BlockType::Hero, 'Hero', 0, ['title' => 'Монтаж заборов']);

        $revision = (new PageRevisionSnapshotBuilder())->build($page, 1, [$block], comment: 'Initial publish');

        self::assertSame(1, $revision->version());
        self::assertSame('/montazh-zaborov/', $revision->path());
        self::assertSame('Монтаж заборов', $revision->seoSnapshot()['ogTitle']);
        self::assertSame(BlockType::Hero->value, $revision->blocksSnapshot()[0]['type']);
        self::assertSame('Initial publish', $revision->comment());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Application\Service\BlockSchemaRegistry;
use App\Module\Content\Application\Service\BlockTypeCatalog;
use App\Module\Content\Domain\Enum\BlockType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BlockSchemaRegistryTest extends TestCase
{
    public function testReturnsSchemaForKnownBlockType(): void
    {
        $schema = $this->registry()->get(BlockType::Hero);

        self::assertSame('hero', $schema->type->value);
        self::assertSame('high', $schema->seoImpact);
    }

    public function testRejectsScriptTagsInHtmlEmbed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry()->validate(BlockType::HtmlEmbed, ['html' => '<script>alert(1)</script>']);
    }

    public function testSliderStructuredDefaultsContainExtendedFields(): void
    {
        $schema = $this->registry()->get(BlockType::Slider);

        self::assertArrayHasKey('items', $schema->defaultContent);
        $items = $schema->defaultContent['items'];
        self::assertIsArray($items);
        self::assertNotEmpty($items);
        self::assertIsArray($items[0]);
        self::assertSame('Заголовок слайда', $items[0]['title'] ?? null);
        self::assertSame('Подробнее', $items[0]['buttonLabel'] ?? null);
        self::assertSame(4500, $schema->defaultSettings['delayMs'] ?? null);
        self::assertSame('slide', $schema->defaultSettings['effect'] ?? null);
        self::assertSame(500, $schema->defaultSettings['speedMs'] ?? null);

        $a11yLabels = $schema->defaultSettings['a11yLabels'] ?? null;
        self::assertIsArray($a11yLabels);
        self::assertSame('Предыдущий слайд', $a11yLabels['prevSlide'] ?? null);

        $cardsEffect = $schema->defaultSettings['cardsEffect'] ?? null;
        self::assertIsArray($cardsEffect);
        self::assertSame(6, $cardsEffect['perSlideOffset'] ?? null);

        $coverflowEffect = $schema->defaultSettings['coverflowEffect'] ?? null;
        self::assertIsArray($coverflowEffect);
        self::assertSame(18, $coverflowEffect['rotate'] ?? null);
    }

    public function testSchemasExposeCanonicalTypeForLegacyBlocks(): void
    {
        $registry = $this->registry();

        self::assertSame('hero.classic', $registry->get(BlockType::Hero)->canonicalType);
        self::assertSame('rich-text', $registry->get(BlockType::Text)->toArray()['canonicalType']);
        self::assertNull($registry->get(BlockType::HtmlEmbed)->canonicalType);
        self::assertNull($registry->get(BlockType::HeroClassic)->canonicalType);
    }

    private function registry(): BlockSchemaRegistry
    {
        return new BlockSchemaRegistry(new BlockTypeCatalog(\dirname(__DIR__, 3).'/config/content/block-types.json'));
    }
}

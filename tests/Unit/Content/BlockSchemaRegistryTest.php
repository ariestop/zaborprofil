<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Application\Service\BlockSchemaRegistry;
use App\Module\Content\Domain\Enum\BlockType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BlockSchemaRegistryTest extends TestCase
{
    public function testReturnsSchemaForKnownBlockType(): void
    {
        $schema = (new BlockSchemaRegistry())->get(BlockType::Hero);

        self::assertSame('hero', $schema->type->value);
        self::assertSame('high', $schema->seoImpact);
    }

    public function testRejectsScriptTagsInHtmlEmbed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new BlockSchemaRegistry())->validate(BlockType::HtmlEmbed, ['html' => '<script>alert(1)</script>']);
    }

    public function testSliderStructuredDefaultsContainExtendedFields(): void
    {
        $schema = (new BlockSchemaRegistry())->get(BlockType::Slider);

        self::assertArrayHasKey('items', $schema->defaultContent);
        self::assertIsArray($schema->defaultContent['items']);
        self::assertNotEmpty($schema->defaultContent['items']);
        self::assertSame('Заголовок слайда', $schema->defaultContent['items'][0]['title'] ?? null);
        self::assertSame('Подробнее', $schema->defaultContent['items'][0]['buttonLabel'] ?? null);
        self::assertSame(4500, $schema->defaultSettings['delayMs'] ?? null);
    }
}

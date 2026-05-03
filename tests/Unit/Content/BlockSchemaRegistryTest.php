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
}

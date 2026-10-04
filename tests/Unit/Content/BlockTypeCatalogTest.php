<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Application\Service\BlockSchemaRegistry;
use App\Module\Content\Application\Service\BlockTypeCatalog;
use App\Module\Content\Domain\Enum\BlockType;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BlockTypeCatalogTest extends TestCase
{
    public function testCatalogMatchesBlockTypeEnumExactly(): void
    {
        $catalog = $this->catalog();
        $enumValues = array_map(static fn (BlockType $type): string => $type->value, BlockType::cases());

        $expected = $enumValues;
        $actual = $catalog->allTypes();
        sort($expected);
        sort($actual);

        self::assertSame($expected, $actual, 'config/content/block-types.json и enum BlockType должны содержать одинаковые типы.');
        self::assertSame($actual, array_values(array_unique($actual)), 'Типы в реестре не должны повторяться.');
    }

    public function testEveryEnumCaseHasSchema(): void
    {
        $registry = new BlockSchemaRegistry($this->catalog());

        foreach (BlockType::cases() as $type) {
            self::assertSame($type, $registry->get($type)->type);
        }
    }

    public function testLegacyAliasesPointToStructuredTypes(): void
    {
        $catalog = $this->catalog();

        foreach ($catalog->legacyAliases() as $legacy => $canonical) {
            self::assertNotNull(BlockType::tryFrom($legacy), \sprintf('Legacy type "%s" is missing in BlockType.', $legacy));
            if ($canonical !== null) {
                self::assertContains($canonical, $catalog->structuredTypes(), \sprintf('Alias target "%s" is not a structured type.', $canonical));
            }
        }

        self::assertTrue($catalog->isLegacy(BlockType::Hero));
        self::assertSame('hero.classic', $catalog->canonicalFor(BlockType::Hero));
        self::assertFalse($catalog->isLegacy(BlockType::HeroClassic));
        self::assertNull($catalog->canonicalFor(BlockType::HeroClassic));
    }

    public function testRejectsInvalidCatalogFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'block-types');
        self::assertIsString($path);
        file_put_contents($path, '{"structured": "oops"}');

        try {
            $this->expectException(RuntimeException::class);
            new BlockTypeCatalog($path);
        } finally {
            unlink($path);
        }
    }

    private function catalog(): BlockTypeCatalog
    {
        return new BlockTypeCatalog(\dirname(__DIR__, 3).'/config/content/block-types.json');
    }
}

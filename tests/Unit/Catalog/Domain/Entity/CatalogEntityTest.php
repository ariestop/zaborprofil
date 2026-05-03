<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Entity;

use App\Module\Catalog\Domain\Entity\Category;
use App\Module\Catalog\Domain\Entity\Product;
use App\Module\Catalog\Domain\Entity\Variant;
use App\Module\Catalog\Domain\Enum\ProductStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CatalogEntityTest extends TestCase
{
    public function testProductExposesVariantData(): void
    {
        $category = new Category('Заборы', 'zabory', '/catalog/zabory/');
        $product = new Product('Забор жалюзи', 'zabor-jaluzi', '/catalog/zabory/zabor-jaluzi/', ProductStatus::Published, category: $category);
        $variant = new Variant($product, 'ZBR-001', 'Высота 2м', 1250000);

        $payload = $product->toArray();

        self::assertSame((string) $category->id(), $payload['categoryId']);
        self::assertSame(ProductStatus::Published->value, $payload['status']);
        self::assertIsArray($payload['variants']);
        $variantPayload = self::firstArray($payload['variants']);
        self::assertSame((string) $variant->id(), $variantPayload['id'] ?? null);
        self::assertSame('ZBR-001', $variantPayload['sku'] ?? null);
        self::assertSame(1250000, $variantPayload['priceCents'] ?? null);
    }

    public function testVariantRejectsNegativePrice(): void
    {
        $product = new Product('Забор', 'zabor', '/catalog/zabor/');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Catalog variant price cannot be negative.');

        new Variant($product, 'ZBR-002', 'Неверная цена', -1);
    }

    /**
     * @return array<string, mixed>
     */
    private static function firstArray(mixed $value): array
    {
        self::assertIsArray($value);
        self::assertIsArray($value[0] ?? null);

        $first = [];
        foreach ($value[0] as $key => $item) {
            self::assertIsString($key);
            $first[$key] = $item;
        }

        return $first;
    }
}

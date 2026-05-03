<?php

declare(strict_types=1);

namespace App\Module\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Catalog\Infrastructure\Doctrine\Repository\DoctrineVariantRepository::class)]
#[ORM\Table(name: 'catalog_variants')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_variants_sku', columns: ['sku'])]
#[ORM\Index(name: 'idx_catalog_variants_product_sort', columns: ['product_id', 'sort_order'])]
#[ORM\Index(name: 'idx_catalog_variants_active', columns: ['is_active'])]
final class Variant
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(length: 80)]
    private string $sku;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(name: 'price_cents')]
    private int $priceCents;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder;

    #[ORM\Column(name: 'is_active')]
    private bool $active;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        Product $product,
        string $sku,
        string $title,
        int $priceCents,
        string $currency = 'RUB',
        int $sortOrder = 0,
        bool $active = true,
    ) {
        $this->id = new Ulid();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->product = $product;
        $this->sku = self::normalizeSku($sku);
        $this->title = self::required($title, 'Catalog variant title cannot be empty.');
        $this->priceCents = self::normalizePrice($priceCents);
        $this->currency = self::normalizeCurrency($currency);
        $this->sortOrder = $sortOrder;
        $this->active = $active;
        $product->addVariant($this);
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function product(): Product
    {
        return $this->product;
    }

    public function update(string $sku, string $title, int $priceCents, string $currency, int $sortOrder, bool $active): void
    {
        $this->sku = self::normalizeSku($sku);
        $this->title = self::required($title, 'Catalog variant title cannot be empty.');
        $this->priceCents = self::normalizePrice($priceCents);
        $this->currency = self::normalizeCurrency($currency);
        $this->sortOrder = $sortOrder;
        $this->active = $active;
        $this->updatedAt = new DateTimeImmutable();
        $this->product->touch();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'productId' => (string) $this->product->id(),
            'sku' => $this->sku,
            'title' => $this->title,
            'priceCents' => $this->priceCents,
            'currency' => $this->currency,
            'sortOrder' => $this->sortOrder,
            'isActive' => $this->active,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private static function required(string $value, string $message): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException($message);
        }

        return $normalized;
    }

    private static function normalizeSku(string $sku): string
    {
        $normalized = strtoupper(trim($sku));

        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,79}$/', $normalized)) {
            throw new InvalidArgumentException('Catalog variant SKU must contain 2-80 latin letters, numbers, dots, underscores or hyphens.');
        }

        return $normalized;
    }

    private static function normalizePrice(int $priceCents): int
    {
        if ($priceCents < 0) {
            throw new InvalidArgumentException('Catalog variant price cannot be negative.');
        }

        return $priceCents;
    }

    private static function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        if (!preg_match('/^[A-Z]{3}$/', $normalized)) {
            throw new InvalidArgumentException('Catalog variant currency must be an ISO 4217 code.');
        }

        return $normalized;
    }
}

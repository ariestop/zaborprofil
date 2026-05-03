<?php

declare(strict_types=1);

namespace App\Module\Catalog\Domain\Entity;

use App\Module\Catalog\Domain\Enum\ProductStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Catalog\Infrastructure\Doctrine\Repository\DoctrineProductRepository::class)]
#[ORM\Table(name: 'catalog_products')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_products_path', columns: ['path'])]
#[ORM\Index(name: 'idx_catalog_products_category_status', columns: ['category_id', 'status'])]
#[ORM\Index(name: 'idx_catalog_products_status', columns: ['status'])]
final class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 180)]
    private string $slug;

    #[ORM\Column(length: 512)]
    private string $path;

    #[ORM\Column(length: 32, enumType: ProductStatus::class)]
    private ProductStatus $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(name: 'meta_description', length: 320, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(name: 'canonical_url', length: 2048, nullable: true)]
    private ?string $canonicalUrl = null;

    #[ORM\Column(name: 'og_title', length: 255, nullable: true)]
    private ?string $ogTitle = null;

    #[ORM\Column(name: 'og_description', length: 320, nullable: true)]
    private ?string $ogDescription = null;

    #[ORM\Column(name: 'og_image', length: 2048, nullable: true)]
    private ?string $ogImage = null;

    #[ORM\Column(name: 'is_indexable')]
    private bool $indexable;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Variant>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Variant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'title' => 'ASC'])]
    private Collection $variants;

    public function __construct(
        string $name,
        string $slug,
        string $path,
        ProductStatus $status = ProductStatus::Draft,
        ?string $summary = null,
        ?string $description = null,
        bool $indexable = true,
        ?Category $category = null,
    ) {
        $this->id = new Ulid();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->variants = new ArrayCollection();
        $this->category = $category;
        $this->name = self::required($name, 'Catalog product name cannot be empty.');
        $this->slug = self::normalizeSlug($slug);
        $this->path = self::normalizePath($path);
        $this->status = $status;
        $this->summary = self::optionalText($summary);
        $this->description = self::optionalText($description);
        $this->indexable = $indexable;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function category(): ?Category
    {
        return $this->category;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function status(): ProductStatus
    {
        return $this->status;
    }

    public function summary(): ?string
    {
        return $this->summary;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function metaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function canonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function ogTitle(): ?string
    {
        return $this->ogTitle;
    }

    public function ogDescription(): ?string
    {
        return $this->ogDescription;
    }

    public function ogImage(): ?string
    {
        return $this->ogImage;
    }

    public function isIndexable(): bool
    {
        return $this->indexable;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return list<Variant>
     */
    public function activeVariants(): array
    {
        return array_values($this->variants->filter(static fn (Variant $variant): bool => $variant->isActive())->toArray());
    }

    public function addVariant(Variant $variant): void
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
        }

        $this->touch();
    }

    public function update(
        string $name,
        string $slug,
        string $path,
        ProductStatus $status,
        ?string $summary,
        ?string $description,
        bool $indexable,
        ?Category $category = null,
    ): void {
        $this->category = $category;
        $this->name = self::required($name, 'Catalog product name cannot be empty.');
        $this->slug = self::normalizeSlug($slug);
        $this->path = self::normalizePath($path);
        $this->status = $status;
        $this->summary = self::optionalText($summary);
        $this->description = self::optionalText($description);
        $this->indexable = $indexable;
        $this->touch();
    }

    public function updateSeoMetadata(
        ?string $metaDescription,
        ?string $canonicalUrl,
        ?string $ogTitle,
        ?string $ogDescription,
        ?string $ogImage,
    ): void {
        $this->metaDescription = self::normalizeOptionalString($metaDescription, 320, 'metaDescription');
        $this->canonicalUrl = self::normalizeOptionalAbsoluteUrl($canonicalUrl, 'canonicalUrl');
        $this->ogTitle = self::normalizeOptionalString($ogTitle, 255, 'ogTitle');
        $this->ogDescription = self::normalizeOptionalString($ogDescription, 320, 'ogDescription');
        $this->ogImage = self::normalizeOptionalAbsoluteUrl($ogImage, 'ogImage');
        $this->touch();
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'categoryId' => $this->category === null ? null : (string) $this->category->id(),
            'name' => $this->name,
            'slug' => $this->slug,
            'path' => $this->path,
            'status' => $this->status->value,
            'summary' => $this->summary,
            'description' => $this->description,
            'metaDescription' => $this->metaDescription,
            'canonicalUrl' => $this->canonicalUrl,
            'ogTitle' => $this->ogTitle,
            'ogDescription' => $this->ogDescription,
            'ogImage' => $this->ogImage,
            'isIndexable' => $this->indexable,
            'variants' => array_map(static fn (Variant $variant): array => $variant->toArray(), $this->variants->toArray()),
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

    private static function normalizeSlug(string $slug): string
    {
        $normalized = trim($slug);

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $normalized)) {
            throw new InvalidArgumentException('Catalog product slug must contain only lowercase latin letters, numbers and hyphens.');
        }

        return $normalized;
    }

    private static function normalizePath(string $path): string
    {
        $normalized = trim($path);

        if ($normalized === '') {
            throw new InvalidArgumentException('Catalog product path cannot be empty.');
        }

        if (!str_starts_with($normalized, '/')) {
            $normalized = '/'.$normalized;
        }

        if ($normalized !== '/' && str_contains($normalized, '//')) {
            throw new InvalidArgumentException('Catalog product path cannot contain double slashes.');
        }

        if (!preg_match('/^\/[a-z0-9_\-\.\/]*$/', $normalized)) {
            throw new InvalidArgumentException('Catalog product path must be a clean URL path.');
        }

        return $normalized;
    }

    private static function optionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private static function normalizeOptionalString(?string $value, int $maxLength, string $field): ?string
    {
        $normalized = self::optionalText($value);

        if ($normalized === null) {
            return null;
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new InvalidArgumentException(\sprintf('Catalog product %s must be at most %d characters.', $field, $maxLength));
        }

        return $normalized;
    }

    private static function normalizeOptionalAbsoluteUrl(?string $value, string $field): ?string
    {
        $normalized = self::normalizeOptionalString($value, 2048, $field);

        if ($normalized === null) {
            return null;
        }

        if (!preg_match('#^https?://#i', $normalized)) {
            throw new InvalidArgumentException(\sprintf('Catalog product %s must be an absolute URL (http:// or https://).', $field));
        }

        return $normalized;
    }
}

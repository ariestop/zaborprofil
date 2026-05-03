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
}

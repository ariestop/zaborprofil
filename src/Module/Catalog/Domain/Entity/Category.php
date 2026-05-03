<?php

declare(strict_types=1);

namespace App\Module\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Catalog\Infrastructure\Doctrine\Repository\DoctrineCategoryRepository::class)]
#[ORM\Table(name: 'catalog_categories')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_categories_path', columns: ['path'])]
#[ORM\Index(name: 'idx_catalog_categories_parent_sort', columns: ['parent_id', 'sort_order'])]
#[ORM\Index(name: 'idx_catalog_categories_active', columns: ['is_active'])]
final class Category
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(length: 180)]
    private string $slug;

    #[ORM\Column(length: 512)]
    private string $path;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder;

    #[ORM\Column(name: 'is_active')]
    private bool $active;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $title,
        string $slug,
        string $path,
        ?string $description = null,
        int $sortOrder = 0,
        bool $active = true,
        ?self $parent = null,
    ) {
        $this->id = new Ulid();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->parent = $parent;
        $this->title = self::required($title, 'Catalog category title cannot be empty.');
        $this->slug = self::normalizeSlug($slug, 'Catalog category slug must contain only lowercase latin letters, numbers and hyphens.');
        $this->path = self::normalizePath($path);
        $this->description = self::optionalText($description);
        $this->sortOrder = $sortOrder;
        $this->active = $active;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function parent(): ?self
    {
        return $this->parent;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function update(string $title, string $slug, string $path, ?string $description, int $sortOrder, bool $active, ?self $parent = null): void
    {
        if ($parent !== null && (string) $parent->id() === (string) $this->id) {
            throw new InvalidArgumentException('Catalog category cannot be its own parent.');
        }

        $this->parent = $parent;
        $this->title = self::required($title, 'Catalog category title cannot be empty.');
        $this->slug = self::normalizeSlug($slug, 'Catalog category slug must contain only lowercase latin letters, numbers and hyphens.');
        $this->path = self::normalizePath($path);
        $this->description = self::optionalText($description);
        $this->sortOrder = $sortOrder;
        $this->active = $active;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'parentId' => $this->parent === null ? null : (string) $this->parent->id(),
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $this->path,
            'description' => $this->description,
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

    private static function normalizeSlug(string $slug, string $message): string
    {
        $normalized = trim($slug);

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $normalized)) {
            throw new InvalidArgumentException($message);
        }

        return $normalized;
    }

    private static function normalizePath(string $path): string
    {
        $normalized = trim($path);

        if ($normalized === '') {
            throw new InvalidArgumentException('Catalog category path cannot be empty.');
        }

        if (!str_starts_with($normalized, '/')) {
            $normalized = '/'.$normalized;
        }

        if ($normalized !== '/' && str_contains($normalized, '//')) {
            throw new InvalidArgumentException('Catalog category path cannot contain double slashes.');
        }

        if (!preg_match('/^\/[a-z0-9_\-\.\/]*$/', $normalized)) {
            throw new InvalidArgumentException('Catalog category path must be a clean URL path.');
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

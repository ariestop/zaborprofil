<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Entity;

use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Enum\PageType;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Content\Infrastructure\Repository\DoctrinePageRepository::class)]
#[ORM\Table(name: 'content_pages')]
#[ORM\Index(name: 'idx_content_pages_status', columns: ['status'])]
#[ORM\Index(name: 'idx_content_pages_parent_id', columns: ['parent_id'])]
#[ORM\Index(name: 'idx_content_pages_deleted_at', columns: ['deleted_at'])]
// Path uniqueness is enforced by a PARTIAL unique index defined in migration
// Version20260501000300 (`uniq_content_pages_path_active WHERE deleted_at IS
// NULL`). Doctrine ORM attribute mapping does not support partial unique
// indexes, so the constraint is intentionally NOT declared here. Application
// code MUST rely on `PageRepositoryInterface::existsByPath()` to reject
// duplicate live paths.
final class Page
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    #[ORM\Column(length: 32, enumType: PageType::class)]
    private PageType $type;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 180)]
    private string $slug;

    #[ORM\Column(length: 512)]
    private string $path;

    #[ORM\Column(length: 255)]
    private string $h1;

    #[ORM\Column(length: 32, enumType: PageStatus::class)]
    private PageStatus $status = PageStatus::Draft;

    #[ORM\Column(length: 120)]
    private string $template;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $indexable = true;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    /**
     * @var Collection<int, PageBlock>
     */
    #[ORM\OneToMany(mappedBy: 'page', targetEntity: PageBlock::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    public function __construct(
        PageType $type,
        string $title,
        string $slug,
        string $path,
        string $h1,
        string $template = 'default',
        int $sortOrder = 0,
        bool $indexable = true,
        ?self $parent = null,
    ) {
        $this->id = new Ulid();
        $this->blocks = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->parent = $parent;
        $this->type = $type;
        $this->title = self::required($title, 'Page title cannot be empty.');
        $this->slug = self::normalizeSlug($slug);
        $this->path = self::normalizePath($path);
        $this->h1 = self::required($h1, 'Page h1 cannot be empty.');
        $this->template = self::required($template, 'Page template cannot be empty.');
        $this->sortOrder = $sortOrder;
        $this->indexable = $indexable;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function parent(): ?self
    {
        return $this->parent;
    }

    public function type(): PageType
    {
        return $this->type;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function h1(): string
    {
        return $this->h1;
    }

    public function status(): PageStatus
    {
        return $this->status;
    }

    public function template(): string
    {
        return $this->template;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isIndexable(): bool
    {
        return $this->indexable;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * @return list<PageBlock>
     */
    public function blocks(): array
    {
        return array_values($this->blocks->toArray());
    }

    /**
     * @return list<PageBlock>
     */
    public function enabledBlocks(): array
    {
        return array_values($this->blocks->filter(static fn (PageBlock $block): bool => $block->isEnabled())->toArray());
    }

    public function update(
        PageType $type,
        string $title,
        string $slug,
        string $path,
        string $h1,
        string $template,
        int $sortOrder,
        bool $indexable,
        ?self $parent = null,
    ): void {
        $this->parent = $parent;
        $this->type = $type;
        $this->title = self::required($title, 'Page title cannot be empty.');
        $this->slug = self::normalizeSlug($slug);
        $this->path = self::normalizePath($path);
        $this->h1 = self::required($h1, 'Page h1 cannot be empty.');
        $this->template = self::required($template, 'Page template cannot be empty.');
        $this->sortOrder = $sortOrder;
        $this->indexable = $indexable;
        $this->touch();
    }

    public function publish(): void
    {
        $this->status = PageStatus::Published;
        $this->publishedAt ??= new DateTimeImmutable();
        $this->touch();
    }

    public function archive(): void
    {
        $this->status = PageStatus::Archived;
        $this->touch();
    }

    public function delete(): void
    {
        $this->deletedAt = new DateTimeImmutable();
        $this->archive();
    }

    public function addBlock(PageBlock $block): void
    {
        if (!$this->blocks->contains($block)) {
            $this->blocks->add($block);
        }

        $this->touch();
    }

    public function removeBlock(PageBlock $block): void
    {
        $this->blocks->removeElement($block);
        $this->touch();
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
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
            throw new InvalidArgumentException('Page slug must contain only lowercase latin letters, numbers and hyphens.');
        }

        return $normalized;
    }

    private static function normalizePath(string $path): string
    {
        $normalized = trim($path);

        if ($normalized === '') {
            throw new InvalidArgumentException('Page path cannot be empty.');
        }

        if (!str_starts_with($normalized, '/')) {
            $normalized = '/' . $normalized;
        }

        if ($normalized !== '/' && str_contains($normalized, '//')) {
            throw new InvalidArgumentException('Page path cannot contain double slashes.');
        }

        if (!preg_match('/^\/[a-z0-9_\-\.\/]*$/', $normalized)) {
            throw new InvalidArgumentException('Page path must be a clean URL path.');
        }

        return $normalized;
    }
}

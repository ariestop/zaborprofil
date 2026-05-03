<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Entity;

use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\ValueObject\PageVisibility;
use App\Shared\Domain\Trait\HasSoftDelete;
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
    use HasSoftDelete;

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

    #[ORM\Column(length: 32, enumType: PageVisibility::class)]
    private PageVisibility $visibility = PageVisibility::Public;

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

    #[ORM\Column(name: 'og_type', length: 32, nullable: true)]
    private ?string $ogType = null;

    /**
     * @var list<array<string, mixed>>|null
     */
    #[ORM\Column(name: 'json_ld', type: 'json', nullable: true, options: ['jsonb' => true])]
    private ?array $jsonLd = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $scheduledPublishAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $scheduledUnpublishAt = null;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $updatedBy = null;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $publishedBy = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

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
        PageVisibility $visibility = PageVisibility::Public,
        ?string $createdBy = null,
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
        $this->visibility = $visibility;
        $this->createdBy = self::normalizeOptionalUlidString($createdBy, 'createdBy');
        $this->updatedBy = $this->createdBy;
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

    public function visibility(): PageVisibility
    {
        return $this->visibility;
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

    public function ogType(): ?string
    {
        return $this->ogType;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function jsonLd(): ?array
    {
        return $this->jsonLd;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function scheduledPublishAt(): ?DateTimeImmutable
    {
        return $this->scheduledPublishAt;
    }

    public function scheduledUnpublishAt(): ?DateTimeImmutable
    {
        return $this->scheduledUnpublishAt;
    }

    public function createdBy(): ?string
    {
        return $this->createdBy;
    }

    public function updatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function publishedBy(): ?string
    {
        return $this->publishedBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
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
        ?PageVisibility $visibility = null,
        ?string $updatedBy = null,
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
        $this->visibility = $visibility ?? $this->visibility;
        $this->updatedBy = self::normalizeOptionalUlidString($updatedBy, 'updatedBy');
        $this->touch();
    }

    /**
     * @param list<array<string, mixed>>|null $jsonLd
     */
    public function updateSeoMetadata(
        ?string $metaDescription,
        ?string $canonicalUrl,
        ?string $ogTitle,
        ?string $ogDescription,
        ?string $ogImage,
        ?string $ogType,
        ?array $jsonLd,
    ): void {
        $this->metaDescription = self::normalizeOptionalString($metaDescription, 320, 'metaDescription');
        $this->canonicalUrl = self::normalizeOptionalAbsoluteUrl($canonicalUrl, 'canonicalUrl');
        $this->ogTitle = self::normalizeOptionalString($ogTitle, 255, 'ogTitle');
        $this->ogDescription = self::normalizeOptionalString($ogDescription, 320, 'ogDescription');
        $this->ogImage = self::normalizeOptionalAbsoluteUrl($ogImage, 'ogImage');
        $this->ogType = self::normalizeOptionalString($ogType, 32, 'ogType');
        $this->jsonLd = self::normalizeJsonLd($jsonLd);
        $this->touch();
    }

    public function submitForReview(?string $updatedBy = null): void
    {
        $this->status = PageStatus::Review;
        $this->updatedBy = self::normalizeOptionalUlidString($updatedBy, 'updatedBy');
        $this->touch();
    }

    public function approve(?string $updatedBy = null): void
    {
        $this->status = PageStatus::Approved;
        $this->updatedBy = self::normalizeOptionalUlidString($updatedBy, 'updatedBy');
        $this->touch();
    }

    public function publish(?string $publishedBy = null): void
    {
        $this->status = PageStatus::Published;
        $this->publishedAt ??= new DateTimeImmutable();
        $this->publishedBy = self::normalizeOptionalUlidString($publishedBy, 'publishedBy');
        $this->updatedBy = $this->publishedBy ?? $this->updatedBy;
        $this->scheduledPublishAt = null;
        $this->touch();
    }

    public function schedule(DateTimeImmutable $publishAt, ?DateTimeImmutable $unpublishAt = null, ?string $updatedBy = null): void
    {
        if ($unpublishAt !== null && $unpublishAt <= $publishAt) {
            throw new InvalidArgumentException('Scheduled unpublish date must be after publish date.');
        }

        $this->status = PageStatus::Scheduled;
        $this->scheduledPublishAt = $publishAt;
        $this->scheduledUnpublishAt = $unpublishAt;
        $this->updatedBy = self::normalizeOptionalUlidString($updatedBy, 'updatedBy');
        $this->touch();
    }

    public function unpublish(?string $updatedBy = null): void
    {
        $this->status = PageStatus::Unpublished;
        $this->updatedBy = self::normalizeOptionalUlidString($updatedBy, 'updatedBy');
        $this->touch();
    }

    public function archive(?string $updatedBy = null): void
    {
        $this->status = PageStatus::Archived;
        $this->updatedBy = self::normalizeOptionalUlidString($updatedBy, 'updatedBy');
        $this->touch();
    }

    public function delete(): void
    {
        $this->markDeleted();
        $this->status = PageStatus::Deleted;
        $this->touch();
    }

    public function restoreToDraft(?string $updatedBy = null): void
    {
        $this->deletedAt = null;
        $this->status = PageStatus::Draft;
        $this->updatedBy = self::normalizeOptionalUlidString($updatedBy, 'updatedBy');
        $this->touch();
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

    private static function normalizeOptionalString(?string $value, int $maxLength, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > $maxLength) {
            throw new InvalidArgumentException(\sprintf('Page %s must be at most %d characters.', $field, $maxLength));
        }

        return $trimmed;
    }

    private static function normalizeOptionalUlidString(?string $value, string $field): ?string
    {
        $normalized = self::normalizeOptionalString($value, 26, $field);
        if ($normalized === null) {
            return null;
        }

        Ulid::fromString($normalized);

        return $normalized;
    }

    private static function normalizeOptionalAbsoluteUrl(?string $value, string $field): ?string
    {
        $normalized = self::normalizeOptionalString($value, 2048, $field);

        if ($normalized === null) {
            return null;
        }

        if (!preg_match('#^https?://#i', $normalized)) {
            throw new InvalidArgumentException(\sprintf('Page %s must be an absolute URL (http:// or https://).', $field));
        }

        return $normalized;
    }

    /**
     * Accepts the raw decoded JSON-LD payload (caller may pass anything that
     * resembles a list of associative arrays). Each element is rebuilt into a
     * `array<string, mixed>` so downstream code can rely on string keys.
     *
     * @param array<mixed>|null $jsonLd
     *
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeJsonLd(?array $jsonLd): ?array
    {
        if ($jsonLd === null || $jsonLd === []) {
            return null;
        }

        $normalized = [];

        foreach ($jsonLd as $index => $block) {
            if (!\is_array($block)) {
                throw new InvalidArgumentException(\sprintf('Page jsonLd[%s] must be an associative array.', (string) $index));
            }

            if (!isset($block['@context']) || !isset($block['@type'])) {
                throw new InvalidArgumentException(\sprintf('Page jsonLd[%s] must contain "@context" and "@type" keys.', (string) $index));
            }

            $stringKeyed = [];
            foreach ($block as $key => $value) {
                if (!\is_string($key)) {
                    throw new InvalidArgumentException(\sprintf('Page jsonLd[%s] must contain only string keys.', (string) $index));
                }

                $stringKeyed[$key] = $value;
            }

            $normalized[] = $stringKeyed;
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

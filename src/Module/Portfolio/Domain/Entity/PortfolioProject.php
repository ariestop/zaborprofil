<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Entity;

use App\Module\Portfolio\Domain\Enum\PortfolioMediaKind;
use App\Module\Portfolio\Domain\Enum\PortfolioStatus;
use App\Module\Portfolio\Domain\ValueObject\PortfolioMediaDraft;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

/**
 * Работа портфолио — один объект: фото и видео, описание, характеристики. Третий уровень: /portfolio/<категория>/<slug>/.
 * Медиа хранится упорядоченным списком; главное фото — одно, всегда фото (первое, если не выбрано).
 */
#[ORM\Entity(repositoryClass: \App\Module\Portfolio\Infrastructure\Doctrine\Repository\DoctrinePortfolioProjectRepository::class)]
#[ORM\Table(name: 'portfolio_projects')]
#[ORM\UniqueConstraint(name: 'uniq_portfolio_projects_category_slug', columns: ['category_id', 'slug'])]
#[ORM\Index(name: 'idx_portfolio_projects_status_sort', columns: ['status', 'sort_order'])]
final class PortfolioProject
{
    public const int SUMMARY_MAX_LENGTH = 300;
    public const int SPECS_MAX = 8;
    public const int MEDIA_MAX = 60;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: PortfolioCategory::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: false, onDelete: 'RESTRICT')]
    private PortfolioCategory $category;

    #[ORM\Column(length: 120)]
    private string $slug;

    #[ORM\Column(length: 200)]
    private string $title;

    /** Населённый пункт или район («Саратов, Агафоновка»). */
    #[ORM\Column(length: 160, nullable: true)]
    private ?string $place = null;

    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    #[ORM\Column(length: self::SUMMARY_MAX_LENGTH, nullable: true)]
    private ?string $summary = null;

    /** Описание — HTML из редактора, уже очищенный санитайзером (эмодзи — обычные символы). */
    #[ORM\Column(type: 'text')]
    private string $description = '';

    /** @var list<array{label: string, value: string}> */
    #[ORM\Column(type: 'json')]
    private array $specs = [];

    #[ORM\Column(length: 16, enumType: PortfolioStatus::class)]
    private PortfolioStatus $status = PortfolioStatus::Draft;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder;

    #[ORM\Column(name: 'seo_title', length: 255, nullable: true)]
    private ?string $seoTitle = null;

    #[ORM\Column(name: 'seo_description', length: 320, nullable: true)]
    private ?string $seoDescription = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, PortfolioMedia> */
    #[ORM\OneToMany(targetEntity: PortfolioMedia::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $media;

    public function __construct(PortfolioCategory $category, string $title, string $slug, int $sortOrder = 0)
    {
        $this->id = new Ulid();
        $this->category = $category;
        $this->title = PortfolioText::required($title, 200, 'Укажите название работы.');
        $this->slug = PortfolioText::slug($slug);
        $this->sortOrder = $sortOrder;
        $this->media = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    /**
     * @param list<array{label: string, value: string}> $specs
     */
    public function edit(
        PortfolioCategory $category,
        string $title,
        string $slug,
        ?string $place,
        ?int $year,
        ?string $summary,
        string $description,
        array $specs,
        ?string $seoTitle,
        ?string $seoDescription,
    ): void {
        $this->category = $category;
        $this->title = PortfolioText::required($title, 200, 'Укажите название работы.');
        $this->slug = PortfolioText::slug($slug);
        $this->place = PortfolioText::optional($place, 160);
        if ($year !== null && ($year < 1990 || $year > 2100)) {
            throw new InvalidArgumentException('Год — четыре цифры, например 2025.');
        }
        $this->year = $year;
        $this->summary = PortfolioText::optional($summary, self::SUMMARY_MAX_LENGTH);
        $this->description = trim($description);
        $this->specs = self::normalizeSpecs($specs);
        $this->seoTitle = PortfolioText::optional($seoTitle, 255);
        $this->seoDescription = PortfolioText::optional($seoDescription, 320);
        $this->touch();
    }

    /**
     * Заменяет медиа работы новым упорядоченным списком. Главное фото — отмеченное, иначе первое фото.
     *
     * @param list<PortfolioMediaDraft> $drafts
     */
    public function replaceMedia(array $drafts): void
    {
        if (\count($drafts) > self::MEDIA_MAX) {
            throw new InvalidArgumentException(\sprintf('В работе может быть не больше %d фото и видео.', self::MEDIA_MAX));
        }
        $covers = array_filter($drafts, static fn (PortfolioMediaDraft $draft): bool => $draft->cover);
        if (\count($covers) > 1) {
            throw new InvalidArgumentException('Главное фото может быть только одно.');
        }
        $coverIndex = array_key_first($covers) ?? array_key_first(array_filter($drafts, static fn (PortfolioMediaDraft $draft): bool => $draft->kind === PortfolioMediaKind::Photo));

        $this->media->clear();
        foreach ($drafts as $index => $draft) {
            $this->media->add(new PortfolioMedia($this, $draft->kind, $draft->src, $draft->poster, $draft->caption, $index === $coverIndex, ($index + 1) * 10));
        }
        if ($this->status === PortfolioStatus::Published && $coverIndex === null) {
            throw new InvalidArgumentException('У опубликованной работы должно остаться хотя бы одно фото.');
        }
        $this->touch();
    }

    public function publish(): void
    {
        if ($this->cover() === null) {
            throw new InvalidArgumentException('Добавьте хотя бы одно фото — оно станет обложкой работы.');
        }
        if ($this->status !== PortfolioStatus::Published) {
            $this->status = PortfolioStatus::Published;
            $this->publishedAt ??= new DateTimeImmutable();
        }
        $this->touch();
    }

    public function unpublish(): void
    {
        $this->status = PortfolioStatus::Draft;
        $this->touch();
    }

    public function moveTo(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function category(): PortfolioCategory
    {
        return $this->category;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function place(): ?string
    {
        return $this->place;
    }

    public function year(): ?int
    {
        return $this->year;
    }

    public function summary(): ?string
    {
        return $this->summary;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function specs(): array
    {
        return $this->specs;
    }

    public function status(): PortfolioStatus
    {
        return $this->status;
    }

    public function isPublished(): bool
    {
        return $this->status === PortfolioStatus::Published;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function seoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function seoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function path(): string
    {
        return $this->category->path().$this->slug.'/';
    }

    /**
     * @return list<PortfolioMedia>
     */
    public function media(): array
    {
        $media = array_values($this->media->toArray());
        usort($media, static fn (PortfolioMedia $a, PortfolioMedia $b): int => $a->sortOrder() <=> $b->sortOrder());

        return $media;
    }

    /**
     * @return list<PortfolioMedia>
     */
    public function photos(): array
    {
        return array_values(array_filter($this->media(), static fn (PortfolioMedia $media): bool => $media->isPhoto()));
    }

    /**
     * @return list<PortfolioMedia>
     */
    public function videos(): array
    {
        return array_values(array_filter($this->media(), static fn (PortfolioMedia $media): bool => $media->isVideo()));
    }

    public function cover(): ?PortfolioMedia
    {
        foreach ($this->media() as $media) {
            if ($media->isCover()) {
                return $media;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'categoryId' => (string) $this->category->id(),
            'slug' => $this->slug,
            'title' => $this->title,
            'place' => $this->place,
            'year' => $this->year,
            'summary' => $this->summary,
            'description' => $this->description,
            'specs' => $this->specs,
            'status' => $this->status->value,
            'sortOrder' => $this->sortOrder,
            'seoTitle' => $this->seoTitle,
            'seoDescription' => $this->seoDescription,
            'path' => $this->path(),
            'cover' => $this->cover()?->src(),
            'photoCount' => \count($this->photos()),
            'videoCount' => \count($this->videos()),
            'media' => array_map(static fn (PortfolioMedia $media): array => $media->toArray(), $this->media()),
            'publishedAt' => $this->publishedAt?->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @param list<array{label: string, value: string}> $specs
     *
     * @return list<array{label: string, value: string}>
     */
    private static function normalizeSpecs(array $specs): array
    {
        $result = [];
        foreach ($specs as $spec) {
            $label = trim($spec['label']);
            $value = trim($spec['value']);
            if ($label === '' && $value === '') {
                continue;
            }
            if ($label === '' || $value === '') {
                throw new InvalidArgumentException('У характеристики нужны и название, и значение (например, «Длина» — «42 м»).');
            }
            if (mb_strlen($label) > 40 || mb_strlen($value) > 40) {
                throw new InvalidArgumentException('Название и значение характеристики — не длиннее 40 символов.');
            }
            $result[] = ['label' => $label, 'value' => $value];
        }
        if (\count($result) > self::SPECS_MAX) {
            throw new InvalidArgumentException(\sprintf('Не больше %d характеристик.', self::SPECS_MAX));
        }

        return $result;
    }
}

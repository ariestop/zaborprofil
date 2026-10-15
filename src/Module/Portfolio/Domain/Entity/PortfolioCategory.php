<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Категория портфолио — вид изделия («Заборы Жалюзи», «3D сетка»). Второй уровень раздела: /portfolio/<slug>/.
 */
#[ORM\Entity(repositoryClass: \App\Module\Portfolio\Infrastructure\Doctrine\Repository\DoctrinePortfolioCategoryRepository::class)]
#[ORM\Table(name: 'portfolio_categories')]
#[ORM\UniqueConstraint(name: 'uniq_portfolio_categories_slug', columns: ['slug'])]
final class PortfolioCategory
{
    public const int DESCRIPTION_MAX_LENGTH = 500;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 120)]
    private string $slug;

    #[ORM\Column(length: 180)]
    private string $title;

    /** Короткое название для плиток и вкладок («Жалюзи»); пусто — используется title. */
    #[ORM\Column(name: 'short_title', length: 60, nullable: true)]
    private ?string $shortTitle = null;

    #[ORM\Column(length: self::DESCRIPTION_MAX_LENGTH, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $title, string $slug, ?string $shortTitle = null, ?string $description = null, int $sortOrder = 0)
    {
        $this->id = new Ulid();
        $this->sortOrder = $sortOrder;
        $this->createdAt = new DateTimeImmutable();
        $this->edit($title, $slug, $shortTitle, $description);
    }

    public function edit(string $title, string $slug, ?string $shortTitle, ?string $description): void
    {
        $this->title = PortfolioText::required($title, 180, 'Укажите название категории.');
        $this->slug = PortfolioText::slug($slug);
        $this->shortTitle = PortfolioText::optional($shortTitle, 60);
        $this->description = PortfolioText::optional($description, self::DESCRIPTION_MAX_LENGTH);
        $this->updatedAt = new DateTimeImmutable();
    }

    public function moveTo(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function shortTitle(): string
    {
        return $this->shortTitle ?? $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function path(): string
    {
        return '/portfolio/'.$this->slug.'/';
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'shortTitle' => $this->shortTitle,
            'description' => $this->description,
            'sortOrder' => $this->sortOrder,
            'path' => $this->path(),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}

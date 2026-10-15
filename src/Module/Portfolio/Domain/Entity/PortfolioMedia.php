<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Entity;

use App\Module\Portfolio\Domain\Enum\PortfolioMediaKind;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Фото, загруженное видео или видео VK/Rutube в работе. Порядок и главное фото задаёт PortfolioProject::replaceMedia().
 */
#[ORM\Entity]
#[ORM\Table(name: 'portfolio_media')]
#[ORM\Index(name: 'idx_portfolio_media_project_sort', columns: ['project_id', 'sort_order'])]
final class PortfolioMedia
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: PortfolioProject::class, inversedBy: 'media')]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private PortfolioProject $project;

    #[ORM\Column(length: 16, enumType: PortfolioMediaKind::class)]
    private PortfolioMediaKind $kind;

    /** Фото и видео — путь в медиатеке (/uploads/media/…); для VK/Rutube — адрес встраиваемого плеера. */
    #[ORM\Column(length: 1024)]
    private string $src;

    /** Обложка видео — фото из медиатеки; пусто — первый кадр (загруженное видео) или заставка плеера (VK/Rutube). */
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $poster;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $caption;

    #[ORM\Column(name: 'is_cover')]
    private bool $cover;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder;

    public function __construct(PortfolioProject $project, PortfolioMediaKind $kind, string $src, ?string $poster, ?string $caption, bool $cover, int $sortOrder)
    {
        $this->id = new Ulid();
        $this->project = $project;
        $this->kind = $kind;
        $this->src = $src;
        $this->poster = $poster;
        $this->caption = $caption;
        $this->cover = $cover;
        $this->sortOrder = $sortOrder;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function project(): PortfolioProject
    {
        return $this->project;
    }

    public function kind(): PortfolioMediaKind
    {
        return $this->kind;
    }

    public function isPhoto(): bool
    {
        return $this->kind === PortfolioMediaKind::Photo;
    }

    public function isVideo(): bool
    {
        return $this->kind !== PortfolioMediaKind::Photo;
    }

    public function src(): string
    {
        return $this->src;
    }

    public function poster(): ?string
    {
        return $this->poster;
    }

    public function caption(): ?string
    {
        return $this->caption;
    }

    public function isCover(): bool
    {
        return $this->cover;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'kind' => $this->kind->value,
            'src' => $this->src,
            'poster' => $this->poster,
            'caption' => $this->caption,
            'isCover' => $this->cover,
        ];
    }
}

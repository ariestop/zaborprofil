<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Entity;

use App\Module\Content\Domain\Enum\BlockType;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Content\Infrastructure\Repository\DoctrinePageBlockRepository::class)]
#[ORM\Table(name: 'content_page_blocks')]
#[ORM\Index(name: 'idx_content_page_blocks_page_position', columns: ['page_id', 'position'])]
#[ORM\Index(name: 'idx_content_page_blocks_type', columns: ['type'])]
final class PageBlock
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Page::class, inversedBy: 'blocks')]
    #[ORM\JoinColumn(name: 'page_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Page $page;

    #[ORM\Column(length: 64, enumType: BlockType::class)]
    private BlockType $type;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column]
    private int $position;

    #[ORM\Column(name: 'is_enabled')]
    private bool $enabled = true;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $content;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $settings;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $settings
     */
    public function __construct(
        Page $page,
        BlockType $type,
        string $name,
        int $position,
        array $content = [],
        array $settings = [],
        bool $enabled = true,
    ) {
        if ($position < 0) {
            throw new InvalidArgumentException('Block position cannot be negative.');
        }

        $this->id = new Ulid();
        $this->page = $page;
        $this->type = $type;
        $this->name = self::required($name);
        $this->position = $position;
        $this->content = $content;
        $this->settings = $settings;
        $this->enabled = $enabled;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();

        $page->addBlock($this);
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function page(): Page
    {
        return $this->page;
    }

    public function type(): BlockType
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function content(): array
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->settings;
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
     * @param array<string, mixed> $content
     * @param array<string, mixed> $settings
     */
    public function update(BlockType $type, string $name, array $content, array $settings, bool $enabled): void
    {
        $this->type = $type;
        $this->name = self::required($name);
        $this->content = $content;
        $this->settings = $settings;
        $this->enabled = $enabled;
        $this->touch();
        $this->page->touch();
    }

    public function moveTo(int $position): void
    {
        if ($position < 0) {
            throw new InvalidArgumentException('Block position cannot be negative.');
        }

        $this->position = $position;
        $this->touch();
        $this->page->touch();
    }

    public function disable(): void
    {
        $this->enabled = false;
        $this->touch();
        $this->page->touch();
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    private static function required(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException('Block name cannot be empty.');
        }

        return $normalized;
    }
}

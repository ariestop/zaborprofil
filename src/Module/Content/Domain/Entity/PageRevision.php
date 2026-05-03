<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Content\Infrastructure\Repository\DoctrinePageRevisionRepository::class)]
#[ORM\Table(name: 'content_page_revisions')]
#[ORM\Index(name: 'idx_content_page_revisions_page_version', columns: ['page_id', 'version'])]
#[ORM\Index(name: 'idx_content_page_revisions_created_at', columns: ['created_at'])]
final class PageRevision
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(name: 'page_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Page $page;

    #[ORM\Column]
    private int $version;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private string $h1;

    #[ORM\Column(length: 180)]
    private string $slug;

    #[ORM\Column(length: 512)]
    private string $path;

    #[ORM\Column(length: 32)]
    private string $type;

    #[ORM\Column(length: 120)]
    private string $template;

    #[ORM\Column(name: 'status_snapshot', length: 32)]
    private string $statusSnapshot;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'seo_snapshot', type: 'json', options: ['jsonb' => true])]
    private array $seoSnapshot;

    /**
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(name: 'blocks_snapshot', type: 'json', options: ['jsonb' => true])]
    private array $blocksSnapshot;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'settings_snapshot', type: 'json', options: ['jsonb' => true])]
    private array $settingsSnapshot;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $createdBy;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'change_summary', type: 'json', options: ['jsonb' => true])]
    private array $changeSummary;

    /**
     * @param array<string, mixed>       $seoSnapshot
     * @param list<array<string, mixed>> $blocksSnapshot
     * @param array<string, mixed>       $settingsSnapshot
     * @param array<string, mixed>       $changeSummary
     */
    public function __construct(
        Page $page,
        int $version,
        string $title,
        string $h1,
        string $slug,
        string $path,
        string $type,
        string $template,
        string $statusSnapshot,
        array $seoSnapshot,
        array $blocksSnapshot,
        array $settingsSnapshot,
        ?string $createdBy = null,
        ?string $comment = null,
        array $changeSummary = [],
    ) {
        $this->id = new Ulid();
        $this->page = $page;
        $this->version = $version;
        $this->title = trim($title);
        $this->h1 = trim($h1);
        $this->slug = trim($slug);
        $this->path = trim($path);
        $this->type = $type;
        $this->template = trim($template);
        $this->statusSnapshot = $statusSnapshot;
        $this->seoSnapshot = $seoSnapshot;
        $this->blocksSnapshot = $blocksSnapshot;
        $this->settingsSnapshot = $settingsSnapshot;
        $this->createdBy = $createdBy;
        $this->createdAt = new DateTimeImmutable();
        $this->comment = $comment === null ? null : trim($comment);
        $this->changeSummary = $changeSummary;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function page(): Page
    {
        return $this->page;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function h1(): string
    {
        return $this->h1;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function template(): string
    {
        return $this->template;
    }

    public function statusSnapshot(): string
    {
        return $this->statusSnapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function seoSnapshot(): array
    {
        return $this->seoSnapshot;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function blocksSnapshot(): array
    {
        return $this->blocksSnapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsSnapshot(): array
    {
        return $this->settingsSnapshot;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function createdBy(): ?string
    {
        return $this->createdBy;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return array<string, mixed>
     */
    public function changeSummary(): array
    {
        return $this->changeSummary;
    }
}

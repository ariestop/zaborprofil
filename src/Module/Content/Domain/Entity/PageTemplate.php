<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Entity;

use App\Module\Content\Domain\Enum\PageType;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Content\Infrastructure\Repository\DoctrinePageTemplateRepository::class)]
#[ORM\Table(name: 'content_page_templates')]
#[ORM\UniqueConstraint(name: 'uniq_content_page_templates_code', columns: ['code'])]
#[ORM\Index(name: 'idx_content_page_templates_page_type_active', columns: ['page_type', 'is_active'])]
final class PageTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 120)]
    private string $code;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(name: 'page_type', length: 32, enumType: PageType::class)]
    private PageType $pageType;

    /**
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(name: 'blocks_schema', type: 'json', options: ['jsonb' => true])]
    private array $blocksSchema;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'default_seo', type: 'json', options: ['jsonb' => true])]
    private array $defaultSeo;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'default_settings', type: 'json', options: ['jsonb' => true])]
    private array $defaultSettings;

    #[ORM\Column(name: 'is_system')]
    private bool $system;

    #[ORM\Column(name: 'is_active')]
    private bool $active;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * @param list<array<string, mixed>> $blocksSchema
     * @param array<string, mixed>       $defaultSeo
     * @param array<string, mixed>       $defaultSettings
     */
    public function __construct(
        string $code,
        string $name,
        PageType $pageType,
        array $blocksSchema,
        ?string $description = null,
        array $defaultSeo = [],
        array $defaultSettings = [],
        bool $system = false,
        bool $active = true,
    ) {
        $this->id = new Ulid();
        $this->code = self::normalizeCode($code);
        $this->name = self::required($name, 'Page template name cannot be empty.');
        $this->description = self::optional($description);
        $this->pageType = $pageType;
        $this->blocksSchema = $blocksSchema;
        $this->defaultSeo = $defaultSeo;
        $this->defaultSettings = $defaultSettings;
        $this->system = $system;
        $this->active = $active;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function pageType(): PageType
    {
        return $this->pageType;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function blocksSchema(): array
    {
        return $this->blocksSchema;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultSeo(): array
    {
        return $this->defaultSeo;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultSettings(): array
    {
        return $this->defaultSettings;
    }

    public function isSystem(): bool
    {
        return $this->system;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    private static function normalizeCode(string $code): string
    {
        $normalized = trim($code);
        if (!preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $normalized)) {
            throw new InvalidArgumentException('Page template code must contain lowercase latin letters, numbers and underscores.');
        }

        return $normalized;
    }

    private static function required(string $value, string $message): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            throw new InvalidArgumentException($message);
        }

        return $normalized;
    }

    private static function optional(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}

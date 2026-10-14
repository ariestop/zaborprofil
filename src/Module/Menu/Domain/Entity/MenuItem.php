<?php

declare(strict_types=1);

namespace App\Module\Menu\Domain\Entity;

use App\Module\Menu\Domain\ValueObject\MenuPosition;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

/**
 * Пункт меню сайта. Меню двухуровневое: пункт верхнего уровня (раздел) и его подпункты.
 * Подпункт лежит в той же позиции, что и родитель; проверки дерева — в MenuTreeService.
 */
#[ORM\Entity(repositoryClass: \App\Module\Menu\Infrastructure\Doctrine\Repository\DoctrineMenuItemRepository::class)]
#[ORM\Table(name: 'menu_items')]
#[ORM\Index(name: 'idx_menu_items_position_sort', columns: ['position', 'sort_order'])]
#[ORM\Index(name: 'idx_menu_items_parent', columns: ['parent_id'])]
final class MenuItem
{
    public const int HINT_MAX_LENGTH = 160;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 64)]
    private string $position;

    #[ORM\Column(name: 'parent_id', type: 'ulid', nullable: true)]
    private ?Ulid $parentId = null;

    #[ORM\Column(length: 180)]
    private string $label;

    #[ORM\Column(length: 1024)]
    private string $url;

    /** Короткая подпись под названием (например, «Металлические ламели»), выводится в раскрытом меню. */
    #[ORM\Column(length: self::HINT_MAX_LENGTH, nullable: true)]
    private ?string $hint = null;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder;

    #[ORM\Column(name: 'is_active')]
    private bool $active;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $position,
        string $label,
        string $url,
        int $sortOrder = 0,
        bool $active = true,
        ?Ulid $parentId = null,
        ?string $hint = null,
    ) {
        $this->id = new Ulid();
        $this->position = MenuPosition::normalize($position);
        $this->parentId = $parentId;
        $this->label = self::required($label, 'Укажите название пункта меню.');
        $this->url = self::normalizeUrl($url);
        $this->hint = self::normalizeHint($hint);
        $this->sortOrder = $sortOrder;
        $this->active = $active;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function position(): string
    {
        return $this->position;
    }

    public function parentId(): ?Ulid
    {
        return $this->parentId;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Содержимое пункта: название, адрес, подпись и видимость. Место в дереве меняет place().
     */
    public function edit(string $label, string $url, ?string $hint, bool $active): void
    {
        $this->label = self::required($label, 'Укажите название пункта меню.');
        $this->url = self::normalizeUrl($url);
        $this->hint = self::normalizeHint($hint);
        $this->active = $active;
        $this->touch();
    }

    /**
     * Место пункта: позиция меню, родитель (null — верхний уровень) и порядок среди соседей.
     */
    public function place(string $position, ?Ulid $parentId, int $sortOrder): void
    {
        if ($parentId !== null && $parentId->equals($this->id)) {
            throw new InvalidArgumentException('Пункт меню не может быть вложен сам в себя.');
        }

        $this->position = MenuPosition::normalize($position);
        $this->parentId = $parentId;
        $this->sortOrder = $sortOrder;
        $this->touch();
    }

    /**
     * @return array{id: string, position: string, parentId: string|null, label: string, url: string, hint: string|null, sortOrder: int, isActive: bool, updatedAt: string}
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'position' => $this->position,
            'parentId' => $this->parentId?->toString(),
            'label' => $this->label,
            'url' => $this->url,
            'hint' => $this->hint,
            'sortOrder' => $this->sortOrder,
            'isActive' => $this->active,
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private function touch(): void
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

    private static function normalizeHint(?string $hint): ?string
    {
        $normalized = trim((string) $hint);
        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > self::HINT_MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('Подпись пункта меню — не длиннее %d символов.', self::HINT_MAX_LENGTH));
        }

        return $normalized;
    }

    private static function normalizeUrl(string $url): string
    {
        $normalized = self::required($url, 'Укажите адрес пункта меню.');

        if (!str_starts_with($normalized, '/') && !str_starts_with($normalized, '#') && !preg_match('#^https?://#i', $normalized)) {
            throw new InvalidArgumentException('Адрес пункта меню — путь на сайте (/zabor/), якорь (#contacts) или полная ссылка (https://…).');
        }

        return $normalized;
    }
}

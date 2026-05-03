<?php

declare(strict_types=1);

namespace App\Module\Menu\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Menu\Infrastructure\Doctrine\Repository\DoctrineMenuItemRepository::class)]
#[ORM\Table(name: 'menu_items')]
#[ORM\Index(name: 'idx_menu_items_position_sort', columns: ['position', 'sort_order'])]
final class MenuItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 64)]
    private string $position;

    #[ORM\Column(length: 180)]
    private string $label;

    #[ORM\Column(length: 1024)]
    private string $url;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder;

    #[ORM\Column(name: 'is_active')]
    private bool $active;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $position, string $label, string $url, int $sortOrder = 0, bool $active = true)
    {
        $this->id = new Ulid();
        $this->position = self::required($position, 'Menu position cannot be empty.');
        $this->label = self::required($label, 'Menu label cannot be empty.');
        $this->url = self::normalizeUrl($url);
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

    public function update(string $position, string $label, string $url, int $sortOrder, bool $active): void
    {
        $this->position = self::required($position, 'Menu position cannot be empty.');
        $this->label = self::required($label, 'Menu label cannot be empty.');
        $this->url = self::normalizeUrl($url);
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
            'position' => $this->position,
            'label' => $this->label,
            'url' => $this->url,
            'sortOrder' => $this->sortOrder,
            'isActive' => $this->active,
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

    private static function normalizeUrl(string $url): string
    {
        $normalized = self::required($url, 'Menu URL cannot be empty.');

        if (!str_starts_with($normalized, '/') && !preg_match('#^https?://#i', $normalized)) {
            throw new InvalidArgumentException('Menu URL must be a site path or an absolute URL.');
        }

        return $normalized;
    }
}

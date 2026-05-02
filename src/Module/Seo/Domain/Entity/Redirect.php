<?php

declare(strict_types=1);

namespace App\Module\Seo\Domain\Entity;

use App\Module\Seo\Infrastructure\Doctrine\Repository\DoctrineRedirectRepository;
use App\Shared\Domain\Contract\TimestampedEntityInterface;
use App\Shared\Domain\Trait\HasTimestamps;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: DoctrineRedirectRepository::class)]
#[ORM\Table(name: 'seo_redirects')]
#[ORM\Index(name: 'idx_seo_redirects_source_path', columns: ['source_path'])]
#[ORM\Index(name: 'idx_seo_redirects_active', columns: ['is_active'])]
class Redirect implements TimestampedEntityInterface
{
    use HasTimestamps;

    private const array ALLOWED_STATUS_CODES = [301, 302, 307, 308];

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 512)]
    private string $sourcePath;

    #[ORM\Column(length: 1024)]
    private string $targetPath;

    #[ORM\Column]
    private int $statusCode;

    #[ORM\Column(name: 'is_active')]
    private bool $active = true;

    #[ORM\Column]
    private int $hitCount = 0;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastHitAt = null;

    public function __construct(string $sourcePath, string $targetPath, int $statusCode = 301, bool $active = true)
    {
        $this->id = new Ulid();
        $this->sourcePath = self::normalizeSourcePath($sourcePath);
        $this->targetPath = self::normalizeTargetPath($targetPath);
        $this->statusCode = self::normalizeStatusCode($statusCode);
        $this->active = $active;
        $this->initializeTimestamps();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function sourcePath(): string
    {
        return $this->sourcePath;
    }

    public function targetPath(): string
    {
        return $this->targetPath;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function hitCount(): int
    {
        return $this->hitCount;
    }

    public function lastHitAt(): ?DateTimeImmutable
    {
        return $this->lastHitAt;
    }

    public function update(string $targetPath, int $statusCode, bool $active): void
    {
        $this->targetPath = self::normalizeTargetPath($targetPath);
        $this->statusCode = self::normalizeStatusCode($statusCode);
        $this->active = $active;
        $this->touch();
    }

    public function registerHit(): void
    {
        ++$this->hitCount;
        $this->lastHitAt = new DateTimeImmutable();
        $this->touch();
    }

    public static function normalizeSourcePath(string $path): string
    {
        $normalized = trim($path);

        if ($normalized === '') {
            throw new InvalidArgumentException('Redirect source path cannot be empty.');
        }

        if (!str_starts_with($normalized, '/')) {
            $normalized = '/'.$normalized;
        }

        if (str_contains($normalized, '://') || str_contains($normalized, '//')) {
            throw new InvalidArgumentException('Redirect source path must be a local clean path.');
        }

        return $normalized;
    }

    private static function normalizeTargetPath(string $path): string
    {
        $normalized = trim($path);

        if ($normalized === '') {
            throw new InvalidArgumentException('Redirect target path cannot be empty.');
        }

        if (!str_contains($normalized, '://') && !str_starts_with($normalized, '/')) {
            $normalized = '/'.$normalized;
        }

        return $normalized;
    }

    private static function normalizeStatusCode(int $statusCode): int
    {
        if (!\in_array($statusCode, self::ALLOWED_STATUS_CODES, true)) {
            throw new InvalidArgumentException('Redirect status code must be one of: 301, 302, 307, 308.');
        }

        return $statusCode;
    }
}

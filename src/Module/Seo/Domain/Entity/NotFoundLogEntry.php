<?php

declare(strict_types=1);

namespace App\Module\Seo\Domain\Entity;

use App\Module\Seo\Infrastructure\Doctrine\Repository\DoctrineNotFoundLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Агрегированная запись журнала 404: один путь — одна строка со счётчиком обращений.
 * Запись и инкремент выполняет {@see DoctrineNotFoundLogRepository::registerHit()} одним SQL-запросом.
 */
#[ORM\Entity(repositoryClass: DoctrineNotFoundLogRepository::class)]
#[ORM\Table(name: 'seo_not_found_log')]
#[ORM\UniqueConstraint(name: 'uniq_seo_not_found_path_hash', columns: ['path_hash'])]
#[ORM\Index(name: 'idx_seo_not_found_last_seen', columns: ['last_seen_at'])]
#[ORM\Index(name: 'idx_seo_not_found_hits', columns: ['hit_count'])]
class NotFoundLogEntry
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 512)]
    private string $path;

    #[ORM\Column(length: 40)]
    private string $pathHash;

    #[ORM\Column]
    private int $hitCount;

    #[ORM\Column]
    private DateTimeImmutable $firstSeenAt;

    #[ORM\Column]
    private DateTimeImmutable $lastSeenAt;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $referrer;

    public function __construct(string $path, ?string $referrer = null, ?DateTimeImmutable $seenAt = null)
    {
        $seenAt ??= new DateTimeImmutable();

        $this->id = new Ulid();
        $this->path = $path;
        $this->pathHash = self::hashPath($path);
        $this->hitCount = 1;
        $this->firstSeenAt = $seenAt;
        $this->lastSeenAt = $seenAt;
        $this->referrer = $referrer;
    }

    public static function hashPath(string $path): string
    {
        return sha1($path);
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function pathHash(): string
    {
        return $this->pathHash;
    }

    public function hitCount(): int
    {
        return $this->hitCount;
    }

    public function firstSeenAt(): DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function lastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function referrer(): ?string
    {
        return $this->referrer;
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Media\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Media\Infrastructure\Doctrine\Repository\DoctrineMediaAssetRepository::class)]
#[ORM\Table(name: 'media_assets')]
#[ORM\Index(name: 'idx_media_assets_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_media_assets_mime_type', columns: ['mime_type'])]
final class MediaAsset
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 1024)]
    private string $publicPath;

    #[ORM\Column(length: 120)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    #[ORM\Column(nullable: true)]
    private ?int $width;

    #[ORM\Column(nullable: true)]
    private ?int $height;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $originalName,
        string $filename,
        string $publicPath,
        string $mimeType,
        int $size,
        ?int $width,
        ?int $height,
    ) {
        if ($size <= 0) {
            throw new InvalidArgumentException('Media asset size must be positive.');
        }

        $this->id = new Ulid();
        $this->originalName = self::required($originalName, 'Media asset original name cannot be empty.');
        $this->filename = self::required($filename, 'Media asset filename cannot be empty.');
        $this->publicPath = self::required($publicPath, 'Media asset public path cannot be empty.');
        $this->mimeType = self::required($mimeType, 'Media asset MIME type cannot be empty.');
        $this->size = $size;
        $this->width = $width;
        $this->height = $height;
        $this->createdAt = new DateTimeImmutable();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => (string) $this->id,
            'originalName' => $this->originalName,
            'filename' => $this->filename,
            'publicPath' => $this->publicPath,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
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
}

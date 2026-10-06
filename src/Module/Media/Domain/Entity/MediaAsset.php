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
#[ORM\Index(name: 'idx_media_assets_file_hash', columns: ['file_hash'])]
#[ORM\Index(name: 'idx_media_assets_folder', columns: ['folder'])]
#[ORM\Index(name: 'idx_media_assets_public_path', columns: ['public_path'], options: ['lengths' => [191]])]
final class MediaAsset
{
    public const int METADATA_MAX_LENGTH = 255;
    public const int DESCRIPTION_MAX_LENGTH = 2000;
    public const int FOLDER_MAX_LENGTH = 120;
    public const string FOLDER_NONE = '__none__';
    public const int FOCAL_MIN = 0;
    public const int FOCAL_MAX = 100;

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

    /**
     * @var list<array{type: string, publicPath: string, width: int|null, height: int|null, mimeType: string, size: int}>
     */
    #[ORM\Column(type: 'json')]
    private array $variants;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: self::FOLDER_MAX_LENGTH, nullable: true)]
    private ?string $folder = null;

    #[ORM\Column(name: 'file_hash', length: 64, nullable: true)]
    private ?string $fileHash = null;

    #[ORM\Column(name: 'focal_x', type: 'smallint', nullable: true, options: ['unsigned' => true])]
    private ?int $focalX = null;

    #[ORM\Column(name: 'focal_y', type: 'smallint', nullable: true, options: ['unsigned' => true])]
    private ?int $focalY = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    /**
     * @param array<mixed> $variants
     */
    public function __construct(
        string $originalName,
        string $filename,
        string $publicPath,
        string $mimeType,
        int $size,
        ?int $width,
        ?int $height,
        array $variants = [],
        ?string $fileHash = null,
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
        $this->variants = self::normalizeVariants($variants);
        $this->fileHash = self::normalizeHash($fileHash);
        $this->createdAt = new DateTimeImmutable();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function publicPath(): string
    {
        return $this->publicPath;
    }

    /**
     * @return list<string>
     */
    public function allPublicPaths(): array
    {
        return array_values(array_unique([
            $this->publicPath,
            ...array_map(static fn (array $variant): string => $variant['publicPath'], $this->variants),
        ]));
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function width(): ?int
    {
        return $this->width;
    }

    public function height(): ?int
    {
        return $this->height;
    }

    public function alt(): ?string
    {
        return $this->alt;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function folder(): ?string
    {
        return $this->folder;
    }

    public function fileHash(): ?string
    {
        return $this->fileHash;
    }

    public function updateMetadata(?string $alt, ?string $title, ?string $description = null, ?string $folder = null): void
    {
        $this->alt = self::optionalText($alt, 'alt');
        $this->title = self::optionalText($title, 'title');
        $this->description = self::optionalText($description, 'description', self::DESCRIPTION_MAX_LENGTH);
        $this->folder = self::normalizeFolder($folder);
    }

    public function focalX(): ?int
    {
        return $this->focalX;
    }

    public function focalY(): ?int
    {
        return $this->focalY;
    }

    /**
     * Фокальная точка в процентах от ширины/высоты (0–100); обе координаты задаются или очищаются вместе.
     */
    public function updateFocalPoint(?int $x, ?int $y): void
    {
        if ($x === null && $y === null) {
            $this->focalX = null;
            $this->focalY = null;

            return;
        }

        if ($x === null || $y === null) {
            throw new InvalidArgumentException('Media asset focal point requires both coordinates.');
        }

        foreach ([$x, $y] as $coordinate) {
            if ($coordinate < self::FOCAL_MIN || $coordinate > self::FOCAL_MAX) {
                throw new InvalidArgumentException(\sprintf('Media asset focal point must be between %d and %d.', self::FOCAL_MIN, self::FOCAL_MAX));
            }
        }

        $this->focalX = $x;
        $this->focalY = $y;
    }

    public function attachFileHash(string $fileHash): void
    {
        $this->fileHash = self::normalizeHash($fileHash);
    }

    /**
     * Новые превью после повторной оптимизации файла (например, когда при загрузке в PHP не было WebP).
     *
     * @param array<mixed> $variants
     */
    public function replaceVariants(array $variants, int $size): void
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Media asset size must be positive.');
        }

        $this->variants = self::normalizeVariants($variants);
        $this->size = $size;
    }

    /**
     * @return list<array{type: string, publicPath: string, width: int|null, height: int|null, mimeType: string, size: int}>
     */
    public function variants(): array
    {
        return $this->variants;
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
            'variants' => $this->variants,
            'alt' => $this->alt,
            'title' => $this->title,
            'description' => $this->description,
            'folder' => $this->folder,
            'fileHash' => $this->fileHash,
            'focalX' => $this->focalX,
            'focalY' => $this->focalY,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<mixed> $variants
     *
     * @return list<array{type: string, publicPath: string, width: int|null, height: int|null, mimeType: string, size: int}>
     */
    private static function normalizeVariants(array $variants): array
    {
        $normalized = [];
        foreach ($variants as $variant) {
            if (!\is_array($variant)) {
                throw new InvalidArgumentException('Media variant must be an array.');
            }

            $normalized[] = [
                'type' => self::variantString($variant, 'type'),
                'publicPath' => self::variantString($variant, 'publicPath'),
                'width' => self::variantNullableInt($variant, 'width'),
                'height' => self::variantNullableInt($variant, 'height'),
                'mimeType' => self::variantString($variant, 'mimeType'),
                'size' => self::variantPositiveInt($variant, 'size'),
            ];
        }

        return $normalized;
    }

    private static function optionalText(?string $value, string $field, int $maxLength = self::METADATA_MAX_LENGTH): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new InvalidArgumentException(\sprintf('Media asset %s cannot be longer than %d characters.', $field, $maxLength));
        }

        return $normalized;
    }

    public static function normalizeFolder(?string $value): ?string
    {
        $folder = self::optionalText($value, 'folder', self::FOLDER_MAX_LENGTH);
        if ($folder === null) {
            return null;
        }

        if ($folder === self::FOLDER_NONE || preg_match('/[\x00-\x1F\x7F\/\\\\]/u', $folder) === 1) {
            throw new InvalidArgumentException('Media asset folder contains forbidden characters.');
        }

        return $folder;
    }

    private static function normalizeHash(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $hash = strtolower(trim($value));
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new InvalidArgumentException('Media asset file hash must be a SHA-256 hex digest.');
        }

        return $hash;
    }

    private static function required(string $value, string $message): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException($message);
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $variant
     */
    private static function variantString(array $variant, string $key): string
    {
        $value = $variant[$key] ?? null;
        if (!\is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(\sprintf('Media variant "%s" must be a non-empty string.', $key));
        }

        return trim($value);
    }

    /**
     * @param array<mixed> $variant
     */
    private static function variantNullableInt(array $variant, string $key): ?int
    {
        $value = $variant[$key] ?? null;
        if ($value === null || \is_int($value)) {
            return $value;
        }

        throw new InvalidArgumentException(\sprintf('Media variant "%s" must be an integer or null.', $key));
    }

    /**
     * @param array<mixed> $variant
     */
    private static function variantPositiveInt(array $variant, string $key): int
    {
        $value = $variant[$key] ?? null;
        if (!\is_int($value) || $value <= 0) {
            throw new InvalidArgumentException(\sprintf('Media variant "%s" must be a positive integer.', $key));
        }

        return $value;
    }
}

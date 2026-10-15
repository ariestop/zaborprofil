<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Upload;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

final readonly class UploadValidator
{
    // Публичные: те же ограничения применяет синхронизация медиатеки к файлам, перенесённым вручную.
    public const int MAX_SIZE_BYTES = 10_485_760;
    // Видео (портфолио) крупнее картинок; лимит ниже 64 МБ из client_max_body_size/upload_max_filesize.
    public const int MAX_VIDEO_SIZE_BYTES = 62_914_560;
    public const int MAX_IMAGE_WIDTH = 8_000;
    public const int MAX_IMAGE_HEIGHT = 8_000;

    /**
     * @var array<string, list<string>>
     */
    private const array ALLOWED_MIME_BY_EXTENSION = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif'],
        'pdf' => ['application/pdf', 'application/x-pdf'],
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
    ];

    /**
     * @var list<string>
     */
    private const array VIDEO_EXTENSIONS = ['mp4', 'webm'];

    /**
     * @var list<string>
     */
    private const array DANGEROUS_EXTENSIONS = [
        'php',
        'phtml',
        'phar',
        'html',
        'htm',
        'js',
        'svg',
    ];

    public function validate(UploadedFile $file): ValidatedUpload
    {
        if (!$file->isValid()) {
            throw new UploadSecurityException('Uploaded file is not valid.');
        }

        $extension = strtolower((string) $file->guessExtension());
        $originalExtension = strtolower($file->getClientOriginalExtension());

        if ($extension === '') {
            $extension = $originalExtension;
        }

        if (!\array_key_exists($extension, self::ALLOWED_MIME_BY_EXTENSION)) {
            throw new UploadSecurityException('Uploaded file extension is not allowed.');
        }

        $this->rejectDoubleExtension($file->getClientOriginalName(), $extension);

        $size = $file->getSize();
        $isVideo = \in_array($extension, self::VIDEO_EXTENSIONS, true);
        $maxSize = $isVideo ? self::MAX_VIDEO_SIZE_BYTES : self::MAX_SIZE_BYTES;
        if (!\is_int($size) || $size <= 0 || $size > $maxSize) {
            throw new UploadSecurityException('Uploaded file size is not allowed.');
        }

        $mimeType = (string) $file->getMimeType();
        if (!\in_array($mimeType, self::ALLOWED_MIME_BY_EXTENSION[$extension], true)) {
            throw new UploadSecurityException('Uploaded file MIME type is not allowed.');
        }

        if ($isVideo) {
            $this->validateVideoSignature($file, $extension);
            [$width, $height] = [null, null];
        } else {
            [$width, $height] = $this->validateImageDimensions($file, $extension);
        }

        return new ValidatedUpload(
            $extension,
            $mimeType,
            strtolower((string) new Ulid()).'.'.$extension,
            $width,
            $height,
            $size,
        );
    }

    private function rejectDoubleExtension(string $originalName, string $normalizedExtension): void
    {
        $parts = array_values(array_filter(explode('.', strtolower($originalName)), static fn (string $part): bool => $part !== ''));
        if (\count($parts) <= 2) {
            return;
        }

        array_pop($parts);
        foreach ($parts as $part) {
            if (\in_array($part, self::DANGEROUS_EXTENSIONS, true)) {
                throw new UploadSecurityException('Uploaded file name contains a dangerous double extension.');
            }
        }

        if (\in_array($normalizedExtension, self::DANGEROUS_EXTENSIONS, true)) {
            throw new UploadSecurityException('Uploaded file extension is dangerous.');
        }
    }

    /**
     * MIME из fileinfo смотрит на те же байты, но явная сверка сигнатуры контейнера отсекает файлы,
     * у которых совпал лишь общий заголовок: MP4 — блок `ftyp` с 5-го байта, WebM — EBML с doctype `webm`.
     */
    private function validateVideoSignature(UploadedFile $file, string $extension): void
    {
        $handle = fopen($file->getPathname(), 'rb');
        $header = $handle === false ? false : fread($handle, 64);
        if ($handle !== false) {
            fclose($handle);
        }

        $valid = \is_string($header) && match ($extension) {
            'mp4' => \strlen($header) >= 12 && substr($header, 4, 4) === 'ftyp',
            'webm' => str_starts_with($header, "\x1A\x45\xDF\xA3") && str_contains($header, 'webm'),
            default => false,
        };

        if (!$valid) {
            throw new UploadSecurityException('Uploaded video container cannot be read.');
        }
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function validateImageDimensions(UploadedFile $file, string $extension): array
    {
        if ($extension === 'pdf') {
            return [null, null];
        }

        $dimensions = getimagesize($file->getPathname());
        if ($dimensions === false) {
            throw new UploadSecurityException('Uploaded image dimensions cannot be read.');
        }

        [$width, $height] = $dimensions;
        if ($width <= 0 || $height <= 0 || $width > self::MAX_IMAGE_WIDTH || $height > self::MAX_IMAGE_HEIGHT) {
            throw new UploadSecurityException('Uploaded image dimensions are not allowed.');
        }

        return [$width, $height];
    }
}

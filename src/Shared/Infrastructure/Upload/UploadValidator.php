<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Upload;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

final readonly class UploadValidator
{
    private const int MAX_SIZE_BYTES = 10_485_760;
    private const int MAX_IMAGE_WIDTH = 8_000;
    private const int MAX_IMAGE_HEIGHT = 8_000;

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
    ];

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
        if (!\is_int($size) || $size <= 0 || $size > self::MAX_SIZE_BYTES) {
            throw new UploadSecurityException('Uploaded file size is not allowed.');
        }

        $mimeType = (string) $file->getMimeType();
        if (!\in_array($mimeType, self::ALLOWED_MIME_BY_EXTENSION[$extension], true)) {
            throw new UploadSecurityException('Uploaded file MIME type is not allowed.');
        }

        [$width, $height] = $this->validateImageDimensions($file, $extension);

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

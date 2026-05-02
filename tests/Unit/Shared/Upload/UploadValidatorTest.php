<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Upload;

use App\Shared\Infrastructure\Upload\UploadSecurityException;
use App\Shared\Infrastructure\Upload\UploadValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadValidatorTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir().'/zaborprofil-upload-test-'.bin2hex(random_bytes(4));
        mkdir($this->temporaryDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->temporaryDirectory)) {
            rmdir($this->temporaryDirectory);
        }
    }

    public function testAcceptsSafePngImage(): void
    {
        $file = $this->uploadedPng('image.png');

        $validated = (new UploadValidator())->validate($file);

        self::assertSame('png', $validated->extension);
        self::assertSame('image/png', $validated->mimeType);
        self::assertSame(1, $validated->width);
        self::assertSame(1, $validated->height);
        self::assertStringEndsWith('.png', $validated->safeFilename);
    }

    public function testRejectsDangerousDoubleExtension(): void
    {
        $this->expectException(UploadSecurityException::class);

        (new UploadValidator())->validate($this->uploadedPng('shell.php.png'));
    }

    public function testRejectsSvgByDefault(): void
    {
        $path = $this->temporaryDirectory.'/icon.svg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->expectException(UploadSecurityException::class);

        (new UploadValidator())->validate(new UploadedFile($path, 'icon.svg', 'image/svg+xml', null, true));
    }

    private function uploadedPng(string $originalName): UploadedFile
    {
        $path = $this->temporaryDirectory.'/'.bin2hex(random_bytes(4)).'.png';
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lF9X8QAAAABJRU5ErkJggg=='));

        return new UploadedFile($path, $originalName, 'image/png', null, true);
    }
}

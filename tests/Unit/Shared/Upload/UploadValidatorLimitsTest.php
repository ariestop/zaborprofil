<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Upload;

use App\Shared\Infrastructure\Upload\UploadSecurityException;
use App\Shared\Infrastructure\Upload\UploadValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadValidatorLimitsTest extends TestCase
{
    private const string ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    private const int MAX_SIZE_BYTES = 10_485_760;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/zaborprofil-upload-limits-'.bin2hex(random_bytes(4));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testAcceptsFileExactlyAtSizeLimit(): void
    {
        $png = $this->png();
        $file = $this->file($png.str_repeat("\0", self::MAX_SIZE_BYTES - \strlen($png)), 'png', 'image/png');

        $validated = (new UploadValidator())->validate($file);

        self::assertSame(self::MAX_SIZE_BYTES, $validated->size);
    }

    public function testRejectsFileOneByteOverSizeLimit(): void
    {
        $png = $this->png();
        $file = $this->file($png.str_repeat("\0", self::MAX_SIZE_BYTES - \strlen($png) + 1), 'png', 'image/png');

        $this->expectException(UploadSecurityException::class);
        $this->expectExceptionMessage('size');

        (new UploadValidator())->validate($file);
    }

    public function testRejectsEmptyFile(): void
    {
        $this->expectException(UploadSecurityException::class);

        (new UploadValidator())->validate($this->file('', 'png', 'image/png'));
    }

    #[DataProvider('imageDimensions')]
    public function testImageDimensionsLimit(int $width, int $height, bool $accepted): void
    {
        $file = $this->file($this->pngWithDimensions($width, $height), 'png', 'image/png');

        if (!$accepted) {
            $this->expectException(UploadSecurityException::class);
            $this->expectExceptionMessage('dimensions');
        }

        $validated = (new UploadValidator())->validate($file);

        self::assertSame($width, $validated->width);
        self::assertSame($height, $validated->height);
    }

    /**
     * @return iterable<string, array{0: int, 1: int, 2: bool}>
     */
    public static function imageDimensions(): iterable
    {
        yield 'ровно 8000x8000' => [8000, 8000, true];
        yield 'шире на 1px' => [8001, 100, false];
        yield 'выше на 1px' => [100, 8001, false];
    }

    public function testDeclaredExtensionDoesNotOverrideRealContent(): void
    {
        $file = $this->file("<?php echo 'x';", 'png', 'image/png');

        $this->expectException(UploadSecurityException::class);

        (new UploadValidator())->validate($file);
    }

    public function testRejectsUnsafeNameEvenWhenContentIsAValidImage(): void
    {
        $path = $this->write($this->png(), 'png');

        foreach (['shell.php.png', 'index.html.png', 'a.js.png', 'x.phtml.png'] as $name) {
            try {
                (new UploadValidator())->validate(new UploadedFile($path, $name, 'image/png', null, true));
                self::fail(\sprintf('Имя "%s" должно быть отклонено.', $name));
            } catch (UploadSecurityException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testStoredNameIsGeneratedAndIgnoresClientName(): void
    {
        $file = $this->file($this->png(), 'png', 'image/png', '../../etc/passwd.png');

        $validated = (new UploadValidator())->validate($file);

        self::assertMatchesRegularExpression('/^[0-9a-z]{26}\.png$/', $validated->safeFilename);
    }

    private function file(string $content, string $extension, string $mime, string $clientName = 'upload.png'): UploadedFile
    {
        return new UploadedFile($this->write($content, $extension), $clientName, $mime, null, true);
    }

    private function write(string $content, string $extension): string
    {
        $path = $this->directory.'/'.bin2hex(random_bytes(4)).'.'.$extension;
        file_put_contents($path, $content);

        return $path;
    }

    private function png(): string
    {
        return (string) base64_decode(self::ONE_PIXEL_PNG, true);
    }

    private function pngWithDimensions(int $width, int $height): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)).$chunk('IEND', '');
    }
}

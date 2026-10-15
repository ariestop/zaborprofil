<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Upload;

use App\Shared\Infrastructure\Upload\UploadSecurityException;
use App\Shared\Infrastructure\Upload\UploadValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadValidatorVideoTest extends TestCase
{
    private const int MAX_VIDEO_SIZE_BYTES = 62_914_560;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/zaborprofil-upload-video-'.bin2hex(random_bytes(4));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testAcceptsMp4WithoutDimensions(): void
    {
        $validated = (new UploadValidator())->validate($this->file($this->mp4(), 'mp4', 'video/mp4', 'Забор на даче.mp4'));

        self::assertSame('mp4', $validated->extension);
        self::assertSame('video/mp4', $validated->mimeType);
        self::assertNull($validated->width);
        self::assertNull($validated->height);
        self::assertMatchesRegularExpression('/^[0-9a-z]{26}\.mp4$/', $validated->safeFilename);
    }

    public function testAcceptsWebm(): void
    {
        $validated = (new UploadValidator())->validate($this->file($this->webm(), 'webm', 'video/webm', 'ролик.webm'));

        self::assertSame('webm', $validated->extension);
        self::assertSame('video/webm', $validated->mimeType);
    }

    public function testVideoMayBeLargerThanImageLimit(): void
    {
        $mp4 = $this->mp4();
        $file = $this->file($mp4.str_repeat("\0", self::MAX_VIDEO_SIZE_BYTES - \strlen($mp4)), 'mp4', 'video/mp4');

        self::assertSame(self::MAX_VIDEO_SIZE_BYTES, (new UploadValidator())->validate($file)->size);
    }

    public function testRejectsVideoOverItsLimit(): void
    {
        $mp4 = $this->mp4();
        $file = $this->file($mp4.str_repeat("\0", self::MAX_VIDEO_SIZE_BYTES - \strlen($mp4) + 1), 'mp4', 'video/mp4');

        $this->expectException(UploadSecurityException::class);
        $this->expectExceptionMessage('size');

        (new UploadValidator())->validate($file);
    }

    public function testRejectsQuickTimeMov(): void
    {
        $mov = "\0\0\0\x14ftypqt  \0\0\0\0qt  ".str_repeat("\0", 32);

        $this->expectException(UploadSecurityException::class);
        $this->expectExceptionMessage('extension');

        (new UploadValidator())->validate($this->file($mov, 'mov', 'video/quicktime', 'IMG_0001.MOV'));
    }

    public function testRejectsScriptRenamedToMp4(): void
    {
        $this->expectException(UploadSecurityException::class);

        (new UploadValidator())->validate($this->file("<?php echo 'x';", 'mp4', 'video/mp4'));
    }

    public function testRejectsDangerousDoubleExtension(): void
    {
        $this->expectException(UploadSecurityException::class);
        $this->expectExceptionMessage('dangerous');

        (new UploadValidator())->validate($this->file($this->mp4(), 'mp4', 'video/mp4', 'clip.php.mp4'));
    }

    private function file(string $content, string $extension, string $mime, string $clientName = 'clip.mp4'): UploadedFile
    {
        $path = $this->directory.'/'.bin2hex(random_bytes(4)).'.'.$extension;
        file_put_contents($path, $content);

        return new UploadedFile($path, $clientName, $mime, null, true);
    }

    private function mp4(): string
    {
        return pack('N', 24).'ftypisom'.pack('N', 512).'isomiso2'.pack('N', 8).'free';
    }

    private function webm(): string
    {
        return "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02";
    }
}

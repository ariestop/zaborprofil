<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content\Infrastructure;

use App\Module\Content\Infrastructure\Http\NginxFastcgiCachePurger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class NginxFastcgiCachePurgerTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/zp-fastcgi-cache-'.bin2hex(random_bytes(4));
        mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cacheDir);
    }

    public function testCacheFileFollowsNginxLevelsLayout(): void
    {
        $purger = $this->purger();
        $hash = md5('https://zaborprofil.ru/zabory/');

        self::assertContains(
            $this->cacheDir.'/'.substr($hash, -1).'/'.substr($hash, -3, 2).'/'.$hash,
            $purger->cacheFilesFor('/zabory/'),
        );
    }

    public function testPurgePathRemovesPageWithAndWithoutTrailingSlash(): void
    {
        $purger = $this->purger();
        $files = [
            ...$purger->cacheFilesFor('/zabory/'),
            ...$purger->cacheFilesFor('/'),
        ];
        $unrelated = $purger->cacheFilesFor('/vorota/')[0];
        foreach ([...$files, $unrelated] as $file) {
            $this->touchCacheFile($file);
        }

        $purger->purgePath('zabory');

        foreach ($purger->cacheFilesFor('/zabory/') as $file) {
            self::assertFileDoesNotExist($file);
        }
        self::assertFileExists($unrelated);
        self::assertFileExists($purger->cacheFilesFor('/')[0]);
    }

    public function testPurgeAllRemovesOnlyCacheEntries(): void
    {
        $purger = $this->purger();
        $entry = $purger->cacheFilesFor('/zabory/')[0];
        $this->touchCacheFile($entry);
        $foreign = $this->cacheDir.'/notes.txt';
        file_put_contents($foreign, 'keep');

        $purger->purgeAll();

        self::assertFileDoesNotExist($entry);
        self::assertFileExists($foreign);
    }

    public function testDoesNothingWithoutCacheDirectory(): void
    {
        $purger = new NginxFastcgiCachePurger('', '1:2', 'https://zaborprofil.ru', new NullLogger());
        $entry = $this->purger()->cacheFilesFor('/zabory/')[0];
        $this->touchCacheFile($entry);

        $purger->purgePath('/zabory/');
        $purger->purgeAll();

        self::assertFileExists($entry);
    }

    private function purger(): NginxFastcgiCachePurger
    {
        return new NginxFastcgiCachePurger($this->cacheDir, '1:2', 'https://zaborprofil.ru', new NullLogger());
    }

    private function touchCacheFile(string $file): void
    {
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0o777, true);
        }

        file_put_contents($file, 'cached');
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.'/'.$item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}

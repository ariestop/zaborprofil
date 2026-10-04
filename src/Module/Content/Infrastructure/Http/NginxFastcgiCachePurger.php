<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Http;

use App\Module\Content\Application\Service\PublicHttpCachePurgerInterface;
use App\Module\Content\Application\Service\PublicPagePathNormalizer;
use FilesystemIterator;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Удаляет файлы кэша nginx `fastcgi_cache` напрямую с диска (nginx без коммерческого модуля purge).
 *
 * Имя файла кэша в nginx — md5 от `fastcgi_cache_key`, подкаталоги берутся с конца хеша согласно `levels`.
 * Ключ в конфигурации nginx обязан иметь вид `$scheme://$host<path без query>` — см. docs/23-cache.md.
 * Пока `NGINX_FASTCGI_CACHE_DIR` пуст, сервис ничего не делает.
 */
final readonly class NginxFastcgiCachePurger implements PublicHttpCachePurgerInterface
{
    private const string CACHE_FILE_PATTERN = '/^[0-9a-f]{32}$/';

    private string $cacheDir;

    /** @var list<int> */
    private array $levels;

    private string $origin;

    public function __construct(
        string $cacheDir,
        string $levels,
        string $siteUrl,
        private LoggerInterface $logger,
    ) {
        $this->cacheDir = rtrim(trim($cacheDir), '/');
        $this->levels = self::parseLevels($levels);
        $this->origin = self::originOf($siteUrl);
    }

    public function purgePath(string $path): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        foreach ($this->cacheFilesFor($path) as $file) {
            $this->delete($file);
        }
    }

    public function purgeAll(): void
    {
        if (!$this->isConfigured() || !is_dir($this->cacheDir)) {
            return;
        }

        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->cacheDir, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && preg_match(self::CACHE_FILE_PATTERN, $file->getFilename()) === 1) {
                    $this->delete($file->getPathname());
                }
            }
        } catch (Throwable $exception) {
            $this->logger->warning('Failed to purge nginx fastcgi cache.', ['exception' => $exception]);
        }
    }

    /**
     * @return list<string>
     */
    public function cacheFilesFor(string $path): array
    {
        $normalized = PublicPagePathNormalizer::normalize($path);
        $variants = [$normalized];

        if ($normalized !== '/') {
            $variants[] = str_ends_with($normalized, '/') ? rtrim($normalized, '/') : $normalized.'/';
        }

        return array_map($this->cacheFileForKey(...), array_map(fn (string $variant): string => $this->origin.$variant, $variants));
    }

    private function cacheFileForKey(string $key): string
    {
        $hash = md5($key);
        $directory = $this->cacheDir;
        $offset = \strlen($hash);

        foreach ($this->levels as $length) {
            $offset -= $length;
            $directory .= '/'.substr($hash, $offset, $length);
        }

        return $directory.'/'.$hash;
    }

    private function delete(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        if (!@unlink($file)) {
            $this->logger->warning('Failed to delete nginx fastcgi cache file.', ['file' => $file]);
        }
    }

    private function isConfigured(): bool
    {
        return $this->cacheDir !== '' && str_starts_with($this->cacheDir, '/') && $this->origin !== '';
    }

    /**
     * @return list<int>
     */
    private static function parseLevels(string $levels): array
    {
        $parsed = [];

        foreach (explode(':', $levels) as $part) {
            $length = (int) $part;
            if ($length >= 1 && $length <= 2) {
                $parsed[] = $length;
            }
        }

        return \array_slice($parsed, 0, 3);
    }

    private static function originOf(string $siteUrl): string
    {
        $parts = parse_url($siteUrl);
        $scheme = \is_array($parts) ? ($parts['scheme'] ?? '') : '';
        $host = \is_array($parts) ? ($parts['host'] ?? '') : '';

        if ($scheme === '' || $host === '') {
            return '';
        }

        return strtolower($scheme.'://'.$host);
    }
}

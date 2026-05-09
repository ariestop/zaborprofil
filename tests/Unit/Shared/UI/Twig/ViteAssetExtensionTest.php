<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\UI\Twig;

use App\Shared\UI\Twig\ViteAssetExtension;
use PHPUnit\Framework\TestCase;

final class ViteAssetExtensionTest extends TestCase
{
    private string $manifestFile;

    protected function setUp(): void
    {
        $this->manifestFile = tempnam(sys_get_temp_dir(), 'vite-manifest-').'.json';
        file_put_contents($this->manifestFile, json_encode([
            'assets/site/app.ts' => [
                'file' => 'assets/site-CLweJMmL.js',
                'name' => 'site',
                'src' => 'assets/site/app.ts',
                'isEntry' => true,
                'css' => ['assets/site-ETMbyqVd.css'],
            ],
            'admin/app.ts' => [
                'file' => 'admin-UdkUeM9_.js',
                'name' => 'admin',
                'src' => 'admin/app.ts',
                'isEntry' => true,
                'imports' => ['assets/site/app.ts'],
            ],
        ], \JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        if (is_file($this->manifestFile)) {
            unlink($this->manifestFile);
        }
    }

    public function testRendersScriptTagForEntry(): void
    {
        $ext = new ViteAssetExtension($this->manifestFile, '/build');

        self::assertSame(
            '<script type="module" src="/build/assets/site-CLweJMmL.js"></script>',
            (string) $ext->renderScriptTags('assets/site/app.ts'),
        );
    }

    public function testRendersLinkTagsForEntryCss(): void
    {
        $ext = new ViteAssetExtension($this->manifestFile, '/build');

        self::assertSame(
            '<link rel="stylesheet" href="/build/assets/site-ETMbyqVd.css">',
            (string) $ext->renderLinkTags('assets/site/app.ts'),
        );
    }

    public function testCollectsCssFromImportedEntries(): void
    {
        $ext = new ViteAssetExtension($this->manifestFile, '/build');

        self::assertSame(
            '<link rel="stylesheet" href="/build/assets/site-ETMbyqVd.css">',
            (string) $ext->renderLinkTags('admin/app.ts'),
        );
    }

    public function testThrowsOnUnknownEntry(): void
    {
        $ext = new ViteAssetExtension($this->manifestFile, '/build');

        try {
            $ext->renderScriptTags('assets/missing/app.ts');
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString(
                'Vite manifest does not contain entry "assets/missing/app.ts"',
                $e->getMessage(),
            );
        }
    }

    public function testThrowsWhenManifestMissing(): void
    {
        $ext = new ViteAssetExtension('/tmp/nonexistent-vite-manifest.json', '/build');

        try {
            $ext->renderLinkTags('assets/site/app.ts');
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Vite manifest not found', $e->getMessage());
        }
    }

    public function testResolvesLegacyAdminManifestEntryKey(): void
    {
        $legacyManifest = tempnam(sys_get_temp_dir(), 'vite-manifest-legacy-').'.json';
        file_put_contents($legacyManifest, json_encode([
            'assets/admin/app.ts' => [
                'file' => 'assets/admin-legacy.js',
                'name' => 'admin',
                'src' => 'assets/admin/app.ts',
                'isEntry' => true,
            ],
        ], \JSON_THROW_ON_ERROR));

        try {
            $ext = new ViteAssetExtension($legacyManifest, '/build');
            self::assertSame(
                '<script type="module" src="/build/assets/admin-legacy.js"></script>',
                (string) $ext->renderScriptTags('admin/app.ts'),
            );
        } finally {
            if (is_file($legacyManifest)) {
                unlink($legacyManifest);
            }
        }
    }
}

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
            'assets/admin/app.ts' => [
                'file' => 'assets/admin-UdkUeM9_.js',
                'name' => 'admin',
                'src' => 'assets/admin/app.ts',
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
            (string) $ext->renderLinkTags('assets/admin/app.ts'),
        );
    }

    public function testThrowsOnUnknownEntry(): void
    {
        $ext = new ViteAssetExtension($this->manifestFile, '/build');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vite manifest does not contain entry "assets/missing/app.ts"');
        $ext->renderScriptTags('assets/missing/app.ts');
    }

    public function testThrowsWhenManifestMissing(): void
    {
        $ext = new ViteAssetExtension('/tmp/nonexistent-vite-manifest.json', '/build');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vite manifest not found');
        $ext->renderLinkTags('assets/site/app.ts');
    }

    public function testDevServerModeDoesNotNeedManifest(): void
    {
        $ext = new ViteAssetExtension('/tmp/nonexistent-vite-manifest.json', '/build', 'http://localhost:5173/');

        $html = (string) $ext->renderLinkTags('assets/site/app.ts');

        self::assertStringContainsString('<script type="module" src="http://localhost:5173/@vite/client"></script>', $html);
        self::assertStringContainsString('<script type="module" src="http://localhost:5173/assets/site/app.ts"></script>', $html);
        self::assertStringContainsString('"http://localhost:5173/@react-refresh"', $html);
        self::assertStringNotContainsString('/build/', $html);
    }

    public function testDevServerModeRendersEntryOnlyOncePerPage(): void
    {
        $ext = new ViteAssetExtension($this->manifestFile, '/build', 'http://localhost:5173');

        $first = (string) $ext->renderLinkTags('assets/admin/app.ts');
        $second = (string) $ext->renderScriptTags('assets/admin/app.ts');

        self::assertSame(1, substr_count($first, 'assets/admin/app.ts'));
        self::assertSame('', $second);
    }

    public function testDevServerModeLoadsClientOnceAcrossEntries(): void
    {
        $ext = new ViteAssetExtension($this->manifestFile, '/build', 'http://localhost:5173');

        $first = (string) $ext->renderScriptTags('assets/site/app.ts');
        $second = (string) $ext->renderScriptTags('assets/admin/app.ts');

        self::assertStringContainsString('@vite/client', $first);
        self::assertStringNotContainsString('@vite/client', $second);
        self::assertSame(
            '<script type="module" src="http://localhost:5173/assets/admin/app.ts"></script>',
            $second,
        );
    }

    public function testEmptyDevServerUrlKeepsManifestMode(): void
    {
        $ext = new ViteAssetExtension($this->manifestFile, '/build', '');

        self::assertSame(
            '<script type="module" src="/build/assets/site-CLweJMmL.js"></script>',
            (string) $ext->renderScriptTags('assets/site/app.ts'),
        );
    }
}

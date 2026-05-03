<?php

declare(strict_types=1);

namespace App\Shared\UI\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Reads the Vite-generated `manifest.json` and exposes Twig helpers that
 * render fingerprinted `<link>` and `<script>` tags for a given Vite entry
 * (for example `assets/site/app.ts` or `assets/admin/app.ts`).
 *
 * Production-only manifest mode is supported — running the Vite dev server
 * is intentionally out of scope here; local workflow is `npm run build`.
 */
final class ViteAssetExtension extends AbstractExtension
{
    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $manifest = null;

    public function __construct(
        private readonly string $manifestPath,
        private readonly string $publicBuildPath,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('vite_entry_link_tags', $this->renderLinkTags(...), ['is_safe' => ['html']]),
            new TwigFunction('vite_entry_script_tags', $this->renderScriptTags(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderLinkTags(string $entry): Markup
    {
        $entryData = $this->resolveEntry($entry);
        $cssFiles = $this->collectCssFiles($entryData);

        $tags = '';
        foreach ($cssFiles as $cssFile) {
            $tags .= \sprintf(
                '<link rel="stylesheet" href="%s">',
                htmlspecialchars($this->publicBuildPath.'/'.$cssFile, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
            );
        }

        return new Markup($tags, 'UTF-8');
    }

    public function renderScriptTags(string $entry): Markup
    {
        $entryData = $this->resolveEntry($entry);
        $file = $entryData['file'] ?? null;

        if (!\is_string($file)) {
            return new Markup('', 'UTF-8');
        }

        $url = $this->publicBuildPath.'/'.$file;

        return new Markup(
            \sprintf(
                '<script type="module" src="%s"></script>',
                htmlspecialchars($url, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
            ),
            'UTF-8',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveEntry(string $entry): array
    {
        $manifest = $this->loadManifest();

        if (!isset($manifest[$entry])) {
            throw new \RuntimeException(\sprintf(
                'Vite manifest does not contain entry "%s". Run `npm run build`.',
                $entry,
            ));
        }

        return $manifest[$entry];
    }

    /**
     * Walks the entry import graph to collect every CSS file the entry
     * (and its imported chunks) needs in the page.
     *
     * @param array<string, mixed> $entryData
     *
     * @return list<string>
     */
    private function collectCssFiles(array $entryData): array
    {
        $manifest = $this->loadManifest();
        $visited = [];
        $cssFiles = [];

        $this->walkImports($entryData, $manifest, $visited, $cssFiles);

        return array_values(array_unique($cssFiles));
    }

    /**
     * @param array<string, mixed>                       $entryData
     * @param array<string, array<string, mixed>>        $manifest
     * @param array<string, true>                        $visited
     * @param list<string>                               $cssFiles
     */
    private function walkImports(array $entryData, array $manifest, array &$visited, array &$cssFiles): void
    {
        if (isset($entryData['css']) && \is_array($entryData['css'])) {
            foreach ($entryData['css'] as $cssFile) {
                if (\is_string($cssFile)) {
                    $cssFiles[] = $cssFile;
                }
            }
        }

        if (!isset($entryData['imports']) || !\is_array($entryData['imports'])) {
            return;
        }

        foreach ($entryData['imports'] as $importKey) {
            if (!\is_string($importKey) || isset($visited[$importKey])) {
                continue;
            }

            $visited[$importKey] = true;

            if (isset($manifest[$importKey])) {
                $this->walkImports($manifest[$importKey], $manifest, $visited, $cssFiles);
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadManifest(): array
    {
        if (null !== $this->manifest) {
            return $this->manifest;
        }

        if (!is_file($this->manifestPath)) {
            throw new \RuntimeException(\sprintf(
                'Vite manifest not found at "%s". Run `npm run build` to generate it.',
                $this->manifestPath,
            ));
        }

        $raw = file_get_contents($this->manifestPath);
        if (false === $raw) {
            throw new \RuntimeException(\sprintf('Vite manifest at "%s" is unreadable.', $this->manifestPath));
        }

        try {
            $decoded = json_decode($raw, true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(\sprintf('Vite manifest at "%s" contains invalid JSON.', $this->manifestPath), 0, $e);
        }

        if (!\is_array($decoded)) {
            throw new \RuntimeException(\sprintf('Vite manifest at "%s" must decode to an object.', $this->manifestPath));
        }

        /** @var array<string, array<string, mixed>> $decoded */
        return $this->manifest = $decoded;
    }
}

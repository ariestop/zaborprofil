<?php

declare(strict_types=1);

namespace App\Shared\UI\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Reads the Vite-generated `manifest.json` and exposes Twig helpers that
 * render fingerprinted `<link>` and `<script>` tags for a given Vite entry
 * (for example `assets/site/app.ts` or `admin/app.ts`).
 *
 * Two modes:
 *  - manifest mode (default, production): fingerprinted files from `manifest.json`;
 *  - dev-server mode: when `$devServerUrl` is set (env `VITE_DEV_SERVER_URL`, local
 *    development only) the entries are loaded straight from the running Vite dev
 *    server with HMR, and neither `npm run build` nor the manifest is needed.
 */
final class ViteAssetExtension extends AbstractExtension
{
    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $manifest = null;

    /**
     * Entries already rendered in dev-server mode (a dev entry is one module that
     * pulls in its own CSS, so it must be emitted once per page).
     *
     * @var array<string, true>
     */
    private array $devEntriesRendered = [];

    private bool $devClientRendered = false;

    private readonly ?string $devServerUrl;

    public function __construct(
        private readonly string $manifestPath,
        private readonly string $publicBuildPath,
        ?string $devServerUrl = null,
    ) {
        $url = null === $devServerUrl ? '' : rtrim(trim($devServerUrl), '/');
        $this->devServerUrl = '' === $url ? null : $url;
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
        if (null !== $this->devServerUrl) {
            // In dev the CSS arrives through the entry module itself (injected by Vite),
            // so the entry is emitted here too: the public layout only calls this helper.
            return $this->renderDevEntry($entry);
        }

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
        if (null !== $this->devServerUrl) {
            return $this->renderDevEntry($entry);
        }

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
     * Emits the Vite HMR client (plus the React Fast Refresh preamble, once per
     * page) and the entry module served by the dev server. Repeated calls for the
     * same entry return an empty string so a page that asks for both link and
     * script tags does not load the module twice.
     */
    private function renderDevEntry(string $entry): Markup
    {
        if (isset($this->devEntriesRendered[$entry])) {
            return new Markup('', 'UTF-8');
        }

        $this->devEntriesRendered[$entry] = true;
        $base = (string) $this->devServerUrl;
        $tags = '';

        if (!$this->devClientRendered) {
            $this->devClientRendered = true;

            // Required by @vitejs/plugin-react when the HTML is not served by Vite itself.
            $tags .= \sprintf(
                '<script type="module">import RefreshRuntime from %s;RefreshRuntime.injectIntoGlobalHook(window);window.$RefreshReg$=()=>{};window.$RefreshSig$=()=>(type)=>type;window.__vite_plugin_react_preamble_installed__=true;</script>',
                json_encode($base.'/@react-refresh', \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_THROW_ON_ERROR),
            );
            $tags .= \sprintf(
                '<script type="module" src="%s"></script>',
                htmlspecialchars($base.'/@vite/client', \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
            );
        }

        $tags .= \sprintf(
            '<script type="module" src="%s"></script>',
            htmlspecialchars($base.'/'.ltrim($entry, '/'), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
        );

        return new Markup($tags, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveEntry(string $entry): array
    {
        $manifest = $this->loadManifest();

        foreach ($this->manifestEntryKeys($entry) as $key) {
            if (isset($manifest[$key])) {
                return $manifest[$key];
            }
        }

        throw new \RuntimeException(\sprintf(
            'Vite manifest does not contain entry "%s". Run `npm run build`.',
            $entry,
        ));
    }

    /**
     * Vite uses the repo-relative path to the entry file as the manifest key.
     * After moving admin sources from `assets/admin/` to `admin/`, older builds
     * still expose `assets/admin/app.ts`; newer builds use `admin/app.ts`.
     *
     * @return list<string>
     */
    private function manifestEntryKeys(string $entry): array
    {
        $keys = [$entry];

        if ('admin/app.ts' === $entry) {
            $keys[] = 'assets/admin/app.ts';
        }

        if ('assets/admin/app.ts' === $entry) {
            $keys[] = 'admin/app.ts';
        }

        return $keys;
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

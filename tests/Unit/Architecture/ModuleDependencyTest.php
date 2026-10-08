<?php

declare(strict_types=1);

namespace App\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Направление зависимостей между модулями (docs/06-module-architecture.md).
 *
 * Тест фиксирует текущий граф: новая зависимость модуля от другого модуля роняет тест, пока её не добавят
 * в {@see ALLOWED} осознанно (и не опишут в docs/06). Исчезнувшая зависимость тоже роняет тест — её нужно
 * убрать из списка, чтобы граф не разрастался обратно незаметно. `Shared` не зависит ни от одного модуля.
 *
 * Существующие циклы (Content ↔ Seo, Menu, Settings, Media; Seo ↔ Catalog; Auth ↔ User) перечислены в {@see KNOWN_CYCLES}:
 * разбирать их сейчас не нужно, но новых циклов быть не должно.
 */
final class ModuleDependencyTest extends TestCase
{
    /**
     * Модуль => модули, на классы которых он ссылается.
     *
     * @var array<string, list<string>>
     */
    private const array ALLOWED = [
        'Admin' => ['AuditLog', 'Auth', 'User'],
        'AuditLog' => ['Auth', 'Content', 'Seo', 'Settings', 'User'],
        'Auth' => ['User'],
        'Catalog' => ['Auth', 'Media', 'Seo'],
        'Content' => ['Auth', 'Media', 'Menu', 'Seo', 'Settings', 'User'],
        'Lead' => ['Admin', 'Auth', 'Content', 'User'],
        'Media' => ['Auth', 'Content'],
        'Menu' => ['Auth', 'Content', 'Media'],
        'Seo' => ['Auth', 'Catalog', 'Content', 'Settings'],
        'Settings' => ['Admin', 'Auth', 'Content', 'Media', 'User'],
        'User' => ['Auth'],
    ];

    /**
     * Пары модулей, зависящих друг от друга. Новые циклы запрещены.
     *
     * @var list<array{string, string}>
     */
    private const array KNOWN_CYCLES = [
        ['Auth', 'User'],
        ['Catalog', 'Seo'],
        ['Content', 'Media'],
        ['Content', 'Menu'],
        ['Content', 'Seo'],
        ['Content', 'Settings'],
    ];

    public function testSharedDoesNotDependOnModules(): void
    {
        $graph = $this->actualGraph();

        self::assertSame([], $graph['Shared'] ?? [], 'src/Shared не должен импортировать App\Module\*: общий код выносится в Shared, а не наоборот.');
    }

    public function testModulesDependOnlyOnAllowedModules(): void
    {
        $graph = $this->actualGraph();
        unset($graph['Shared']);

        $unexpected = [];
        $stale = [];
        foreach ($graph as $module => $dependencies) {
            $allowed = self::ALLOWED[$module] ?? [];
            foreach (array_diff($dependencies, $allowed) as $dependency) {
                $unexpected[] = \sprintf('%s -> %s', $module, $dependency);
            }
        }
        foreach (self::ALLOWED as $module => $allowed) {
            foreach (array_diff($allowed, $graph[$module] ?? []) as $dependency) {
                $stale[] = \sprintf('%s -> %s', $module, $dependency);
            }
        }

        self::assertSame([], $unexpected, 'Новая зависимость между модулями. Если она нужна, добавьте её в ModuleDependencyTest::ALLOWED и опишите в docs/06-module-architecture.md; иначе используйте опубликованный интерфейс, событие или Shared.');
        self::assertSame([], $stale, 'Зависимость между модулями исчезла — уберите её из ModuleDependencyTest::ALLOWED и docs/06-module-architecture.md.');
    }

    public function testNoNewDependencyCycles(): void
    {
        $graph = $this->actualGraph();
        unset($graph['Shared']);

        $cycles = [];
        foreach ($graph as $module => $dependencies) {
            foreach ($dependencies as $dependency) {
                if ($module < $dependency && \in_array($module, $graph[$dependency] ?? [], true)) {
                    $cycles[] = [$module, $dependency];
                }
            }
        }
        sort($cycles);

        self::assertSame(self::KNOWN_CYCLES, $cycles, 'Новый цикл между модулями запрещён (docs/06-module-architecture.md, «Циклические зависимости»).');
    }

    /**
     * @return array<string, list<string>> модуль (или `Shared`) => отсортированный список модулей, на которые он ссылается
     */
    private function actualGraph(): array
    {
        $root = \dirname(__DIR__, 3).'/src';
        $graph = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), \strlen($root) + 1);
            $source = match (true) {
                str_starts_with($relative, 'Module/') => explode('/', $relative)[1],
                str_starts_with($relative, 'Shared/') => 'Shared',
                default => null,
            };
            if ($source === null) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all('/\bApp\\\\Module\\\\(\w+)\\\\/', $contents, $matches);
            foreach ($matches[1] as $target) {
                if ($target !== $source) {
                    $graph[$source][$target] = true;
                }
            }
        }

        $result = [];
        foreach ($graph as $source => $targets) {
            $names = array_keys($targets);
            sort($names);
            $result[$source] = $names;
        }

        return $result;
    }
}

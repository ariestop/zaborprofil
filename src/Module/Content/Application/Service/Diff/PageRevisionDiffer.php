<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service\Diff;

use App\Module\Content\Domain\Entity\PageRevision;

/**
 * Сравнивает два снимка страницы: поля, SEO, настройки и блоки.
 *
 * Статус и даты планирования не сравниваются: это не контент. Блоки сопоставляются по id, затем по паре
 * «тип + название» и по позиции, потому что откат пересоздаёт блоки с новыми id.
 *
 * @phpstan-type Change array{field: string, before: scalar|null, after: scalar|null, textDiff?: list<array{op: string, text: string}>}
 * @phpstan-type BlockDiff array{status: string, type: string, name: string, positionBefore: int|null, positionAfter: int|null, changes: list<Change>}
 */
final readonly class PageRevisionDiffer
{
    public const string BLOCK_ADDED = 'added';
    public const string BLOCK_REMOVED = 'removed';
    public const string BLOCK_CHANGED = 'changed';
    public const string BLOCK_UNCHANGED = 'unchanged';

    private const array PAGE_FIELDS = ['title', 'h1', 'slug', 'path', 'type', 'template'];
    private const array SEO_FIELDS = ['isIndexable', 'metaTitle', 'metaDescription', 'canonicalUrl', 'ogTitle', 'ogDescription', 'ogImage', 'ogType', 'jsonLd'];
    private const array SETTINGS_FIELDS = ['visibility', 'sortOrder'];
    private const array BLOCK_FIELDS = ['type', 'name', 'isEnabled', 'visibility'];

    public function __construct(private TextDiffer $text)
    {
    }

    /**
     * @return array{
     *     hasChanges: bool,
     *     summary: array{fields: int, seo: int, settings: int, blocksAdded: int, blocksRemoved: int, blocksChanged: int},
     *     fields: list<Change>,
     *     seo: list<Change>,
     *     settings: list<Change>,
     *     blocks: list<BlockDiff>
     * }
     */
    public function diff(PageRevision $from, PageRevision $to): array
    {
        $fields = $this->compare($this->pageValues($from), $this->pageValues($to), self::PAGE_FIELDS);
        $seo = $this->compare($this->flatten($from->seoSnapshot()), $this->flatten($to->seoSnapshot()), self::SEO_FIELDS);
        $settings = $this->compare($this->flatten($from->settingsSnapshot()), $this->flatten($to->settingsSnapshot()), self::SETTINGS_FIELDS);
        $blocks = $this->diffBlocks($from->blocksSnapshot(), $to->blocksSnapshot());

        $added = $this->countBlocks($blocks, self::BLOCK_ADDED);
        $removed = $this->countBlocks($blocks, self::BLOCK_REMOVED);
        $changed = $this->countBlocks($blocks, self::BLOCK_CHANGED);

        return [
            'hasChanges' => $fields !== [] || $seo !== [] || $settings !== [] || $added + $removed + $changed > 0,
            'summary' => [
                'fields' => \count($fields),
                'seo' => \count($seo),
                'settings' => \count($settings),
                'blocksAdded' => $added,
                'blocksRemoved' => $removed,
                'blocksChanged' => $changed,
            ],
            'fields' => $fields,
            'seo' => $seo,
            'settings' => $settings,
            'blocks' => $blocks,
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    private function pageValues(PageRevision $revision): array
    {
        return [
            'title' => $revision->title(),
            'h1' => $revision->h1(),
            'slug' => $revision->slug(),
            'path' => $revision->path(),
            'type' => $revision->type(),
            'template' => $revision->template(),
        ];
    }

    /**
     * @param array<string, scalar|null> $before
     * @param array<string, scalar|null> $after
     * @param list<string>               $onlyFields если не пусто, сравниваются поля с этим префиксом/именем
     *
     * @return list<Change>
     */
    private function compare(array $before, array $after, array $onlyFields = [], string $prefix = ''): array
    {
        $changes = [];
        foreach ($this->orderedKeys($before, $after) as $key) {
            if ($onlyFields !== [] && !$this->isAllowed($key, $onlyFields)) {
                continue;
            }

            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old === $new) {
                continue;
            }

            $changes[] = $this->change($prefix.$key, $old, $new);
        }

        return $changes;
    }

    /**
     * @param list<string> $allowed
     */
    private function isAllowed(string $key, array $allowed): bool
    {
        return array_any($allowed, fn ($field) => $key === $field || str_starts_with($key, $field.'.') || str_starts_with($key, $field.'['));
    }

    /**
     * @param array<string, scalar|null> $a
     * @param array<string, scalar|null> $b
     *
     * @return list<string>
     */
    private function orderedKeys(array $a, array $b): array
    {
        return array_map(strval(...), array_keys($a + $b));
    }

    /**
     * @return Change
     */
    private function change(string $field, mixed $before, mixed $after): array
    {
        /** @var scalar|null $before */
        /** @var scalar|null $after */
        $change = ['field' => $field, 'before' => $before, 'after' => $after];
        if ((\is_string($before) || $before === null) && (\is_string($after) || $after === null)) {
            $change['textDiff'] = $this->text->diff((string) $before, (string) $after);
        }

        return $change;
    }

    /**
     * @param list<array<string, mixed>> $from
     * @param list<array<string, mixed>> $to
     *
     * @return list<BlockDiff>
     */
    private function diffBlocks(array $from, array $to): array
    {
        $pairs = $this->matchBlocks($from, $to);
        $matchedFrom = array_flip(array_values($pairs));
        $fromRanks = $this->ranks(array_keys($pairs));
        $toRanks = $this->ranks(array_values($pairs));

        $result = [];
        foreach ($to as $toIndex => $block) {
            $fromIndex = array_search($toIndex, $pairs, true);
            if ($fromIndex === false) {
                $result[] = $this->blockEntry(self::BLOCK_ADDED, [], $block, []);

                continue;
            }

            $old = $from[$fromIndex];
            $changes = $this->blockChanges($old, $block);
            if ($fromRanks[$fromIndex] !== $toRanks[$toIndex]) {
                $changes[] = ['field' => 'order', 'before' => $fromRanks[$fromIndex] + 1, 'after' => $toRanks[$toIndex] + 1];
            }

            $result[] = $this->blockEntry($changes === [] ? self::BLOCK_UNCHANGED : self::BLOCK_CHANGED, $old, $block, $changes);
        }

        foreach ($from as $fromIndex => $block) {
            if (!isset($matchedFrom[$fromIndex])) {
                $result[] = $this->blockEntry(self::BLOCK_REMOVED, $block, [], []);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private function sameId(array $old, array $new): bool
    {
        $id = $old['id'] ?? null;

        return \is_string($id) && $id !== '' && $id === ($new['id'] ?? null);
    }

    /**
     * @param list<int> $indexes
     *
     * @return array<int, int> индекс => порядковый номер среди сопоставленных блоков
     */
    private function ranks(array $indexes): array
    {
        sort($indexes);

        return array_flip($indexes);
    }

    /**
     * @param list<array<string, mixed>> $from
     * @param list<array<string, mixed>> $to
     *
     * @return array<int, int> индекс блока «до» => индекс блока «после»
     */
    private function matchBlocks(array $from, array $to): array
    {
        $pairs = [];
        $usedTo = [];

        $match = function (callable $same) use ($from, $to, &$pairs, &$usedTo): void {
            foreach ($from as $fromIndex => $old) {
                if (isset($pairs[$fromIndex])) {
                    continue;
                }

                foreach ($to as $toIndex => $new) {
                    if (!isset($usedTo[$toIndex]) && $same($old, $new, $fromIndex, $toIndex)) {
                        $pairs[$fromIndex] = $toIndex;
                        $usedTo[$toIndex] = true;

                        break;
                    }
                }
            }
        };

        $match($this->sameId(...));
        $match(static fn (array $old, array $new): bool => ($old['type'] ?? null) === ($new['type'] ?? null) && ($old['name'] ?? null) === ($new['name'] ?? null));
        $match(static fn (array $old, array $new, int $i, int $j): bool => ($old['type'] ?? null) === ($new['type'] ?? null) && $i === $j);

        return $pairs;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     *
     * @return list<Change>
     */
    private function blockChanges(array $old, array $new): array
    {
        $changes = [];
        foreach (self::BLOCK_FIELDS as $field) {
            $changes = [...$changes, ...$this->compare($this->scalarsOf($old, [$field]), $this->scalarsOf($new, [$field]))];
        }

        foreach (['content', 'settings'] as $section) {
            $changes = [...$changes, ...$this->compare(
                $this->flatten($old[$section] ?? [], $section),
                $this->flatten($new[$section] ?? [], $section),
            )];
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $block
     * @param list<string>         $fields
     *
     * @return array<string, scalar|null>
     */
    private function scalarsOf(array $block, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $value = $block[$field] ?? null;
            $values[$field] = \is_scalar($value) ? $value : null;
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @param list<Change>         $changes
     *
     * @return BlockDiff
     */
    private function blockEntry(string $status, array $old, array $new, array $changes): array
    {
        $source = $new !== [] ? $new : $old;
        $type = $source['type'] ?? '';
        $name = $source['name'] ?? '';
        $before = $old['position'] ?? null;
        $after = $new['position'] ?? null;

        return [
            'status' => $status,
            'type' => \is_string($type) ? $type : '',
            'name' => \is_string($name) ? $name : '',
            'positionBefore' => \is_int($before) ? $before : null,
            'positionAfter' => \is_int($after) ? $after : null,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<BlockDiff> $blocks
     */
    private function countBlocks(array $blocks, string $status): int
    {
        return \count(array_filter($blocks, static fn (array $block): bool => $block['status'] === $status));
    }

    /**
     * @return array<string, scalar|null>
     */
    private function flatten(mixed $value, string $prefix = ''): array
    {
        if (!\is_array($value)) {
            return $prefix === '' ? [] : [$prefix => \is_scalar($value) ? $value : null];
        }

        if ($value === []) {
            return $prefix === '' ? [] : [$prefix => null];
        }

        $flat = [];
        foreach ($value as $key => $item) {
            $path = $prefix === '' ? (string) $key : (\is_int($key) ? \sprintf('%s[%d]', $prefix, $key) : $prefix.'.'.$key);
            $flat += $this->flatten($item, $path);
        }

        return $flat;
    }
}

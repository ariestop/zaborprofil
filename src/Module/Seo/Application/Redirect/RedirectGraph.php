<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

/**
 * Граф активных редиректов «источник -> внутренний целевой путь». У каждого узла не более одной исходящей дуги,
 * поэтому циклы и цепочки находятся простым обходом.
 */
final class RedirectGraph
{
    private const int MAX_HOPS = 50;

    /**
     * @param array<string, string> $edges
     */
    public function __construct(private array $edges = [])
    {
    }

    public function set(string $source, ?string $target): void
    {
        if ($target === null) {
            unset($this->edges[$source]);

            return;
        }

        $this->edges[$source] = $target;
    }

    /**
     * Путь `source -> target -> ...`, если добавление дуги замкнёт цикл (в том числе через уже существующий цикл).
     *
     * @return list<string>|null
     */
    public function loopFor(string $source, string $target): ?array
    {
        $path = [$source, $target];
        $visited = [$source => true, $target => true];
        $node = $target;

        while ($node !== $source) {
            $next = $this->edges[$node] ?? null;
            if ($next === null || \count($path) > self::MAX_HOPS) {
                return null;
            }

            $path[] = $next;
            if (isset($visited[$next]) && $next !== $source) {
                return $path;
            }

            $visited[$next] = true;
            $node = $next;
        }

        return $path;
    }

    /**
     * Цепочка, которая получится после добавления дуги: `source -> target -> ... -> конец`.
     *
     * @return list<string>
     */
    public function chainFor(string $source, string $target): array
    {
        $path = [$source, $target];
        $node = $target;

        while (isset($this->edges[$node]) && \count($path) <= self::MAX_HOPS) {
            $node = $this->edges[$node];
            if (\in_array($node, $path, true)) {
                break;
            }

            $path[] = $node;
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    public function incomingTo(string $node): array
    {
        $sources = [];
        foreach ($this->edges as $source => $target) {
            if ($target === $node && $source !== $node) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    /**
     * @return list<list<string>> каждый цикл начинается и заканчивается одним и тем же узлом
     */
    public function cycles(): array
    {
        $done = [];
        $cycles = [];

        foreach (array_keys($this->edges) as $start) {
            if (isset($done[$start])) {
                continue;
            }

            $path = [];
            $position = [];
            $node = (string) $start;

            while (!isset($done[$node])) {
                if (isset($position[$node])) {
                    $cycle = \array_slice($path, $position[$node]);
                    $cycle[] = $node;
                    $cycles[] = $cycle;
                    break;
                }

                $position[$node] = \count($path);
                $path[] = $node;

                if (!isset($this->edges[$node])) {
                    break;
                }

                $node = $this->edges[$node];
            }

            foreach ($path as $visited) {
                $done[$visited] = true;
            }
        }

        return $cycles;
    }

    /**
     * Максимальные цепочки минимум из двух переходов, которые не заканчиваются циклом.
     *
     * @return list<list<string>>
     */
    public function chains(): array
    {
        $hasIncoming = array_flip(array_values($this->edges));
        $chains = [];

        foreach (array_keys($this->edges) as $head) {
            $head = (string) $head;
            if (isset($hasIncoming[$head])) {
                continue;
            }

            $path = [$head];
            $node = $head;
            $looped = false;

            while (isset($this->edges[$node])) {
                $node = $this->edges[$node];
                if (\in_array($node, $path, true) || \count($path) > self::MAX_HOPS) {
                    $looped = true;
                    break;
                }

                $path[] = $node;
            }

            if (!$looped && \count($path) >= 3) {
                $chains[] = $path;
            }
        }

        return $chains;
    }
}

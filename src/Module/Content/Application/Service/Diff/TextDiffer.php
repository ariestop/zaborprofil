<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service\Diff;

/**
 * Пословный diff двух строк (LCS). Для очень больших текстов откатывается к замене целиком,
 * чтобы не тратить квадратичную память и время.
 */
final readonly class TextDiffer
{
    public const string EQUAL = 'equal';
    public const string INSERT = 'insert';
    public const string DELETE = 'delete';

    private const int MAX_MATRIX_CELLS = 250_000;

    /**
     * @return list<array{op: string, text: string}>
     */
    public function diff(string $before, string $after): array
    {
        if ($before === $after) {
            return $before === '' ? [] : [['op' => self::EQUAL, 'text' => $before]];
        }

        $a = $this->tokenize($before);
        $b = $this->tokenize($after);
        $n = \count($a);
        $m = \count($b);

        if ($n * $m > self::MAX_MATRIX_CELLS) {
            return $this->replace($before, $after);
        }

        $lengths = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; --$i) {
            for ($j = $m - 1; $j >= 0; --$j) {
                $lengths[$i][$j] = $a[$i] === $b[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $segments = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $this->append($segments, self::EQUAL, $a[$i]);
                ++$i;
                ++$j;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $this->append($segments, self::DELETE, $a[$i]);
                ++$i;
            } else {
                $this->append($segments, self::INSERT, $b[$j]);
                ++$j;
            }
        }

        for (; $i < $n; ++$i) {
            $this->append($segments, self::DELETE, $a[$i]);
        }

        for (; $j < $m; ++$j) {
            $this->append($segments, self::INSERT, $b[$j]);
        }

        return $segments;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return $tokens === false ? [$text] : $tokens;
    }

    /**
     * @return list<array{op: string, text: string}>
     */
    private function replace(string $before, string $after): array
    {
        $segments = [];
        if ($before !== '') {
            $segments[] = ['op' => self::DELETE, 'text' => $before];
        }

        if ($after !== '') {
            $segments[] = ['op' => self::INSERT, 'text' => $after];
        }

        return $segments;
    }

    /**
     * @param list<array{op: string, text: string}> $segments
     */
    private function append(array &$segments, string $op, string $text): void
    {
        $last = \count($segments) - 1;
        if ($last >= 0 && $segments[$last]['op'] === $op) {
            $segments[$last]['text'] .= $text;

            return;
        }

        $segments[] = ['op' => $op, 'text' => $text];
    }
}

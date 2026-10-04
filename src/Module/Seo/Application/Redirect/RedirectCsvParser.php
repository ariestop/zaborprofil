<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

use InvalidArgumentException;

/**
 * Разбирает CSV карты редиректов: `source,target[,status[,active]]`, разделитель `,`, `;` или TAB определяется
 * автоматически, заголовок необязателен.
 */
final class RedirectCsvParser
{
    public const int MAX_ROWS = 2000;
    public const int MAX_BYTES = 1_048_576;

    /**
     * @var array<string, list<string>>
     */
    private const array HEADER_ALIASES = [
        'source' => ['source', 'sourcepath', 'source_path', 'from', 'old', 'old_url', 'oldurl', 'url', 'источник', 'старый url', 'старый адрес'],
        'target' => ['target', 'targetpath', 'target_path', 'to', 'new', 'new_url', 'newurl', 'destination', 'цель', 'новый url', 'новый адрес'],
        'status' => ['status', 'statuscode', 'status_code', 'code', 'код', 'код ответа'],
        'active' => ['active', 'isactive', 'is_active', 'enabled', 'активен', 'активно'],
    ];

    /**
     * @return array{rows: list<array{line: int, source: string, target: string, status: string, active: string}>, errors: list<array{line: int, source: string, message: string}>}
     */
    public function parse(string $csv): array
    {
        if (\strlen($csv) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Файл слишком большой: максимум 1 МБ.');
        }

        if (!mb_check_encoding($csv, 'UTF-8')) {
            throw new InvalidArgumentException('Файл должен быть в кодировке UTF-8.');
        }

        $csv = str_replace(["\r\n", "\r"], "\n", preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv);
        $lines = explode("\n", $csv);
        $delimiter = $this->detectDelimiter($lines);

        $columns = ['source' => 0, 'target' => 1, 'status' => 2, 'active' => 3];
        $rows = [];
        $errors = [];
        $headerChecked = false;

        foreach ($lines as $index => $rawLine) {
            $line = $index + 1;
            if (trim($rawLine) === '' || str_starts_with(ltrim($rawLine), '#')) {
                continue;
            }

            $cells = array_map(static fn (?string $cell): string => trim((string) $cell), str_getcsv($rawLine, $delimiter, '"', ''));

            if (!$headerChecked) {
                $headerChecked = true;
                $mapped = $this->mapHeader($cells);
                if ($mapped !== null) {
                    $columns = $mapped;
                    continue;
                }
            }

            if (\count($rows) + \count($errors) >= self::MAX_ROWS) {
                throw new InvalidArgumentException(\sprintf('Слишком много строк: максимум %d за один импорт.', self::MAX_ROWS));
            }

            $source = $cells[$columns['source']] ?? '';
            $target = $cells[$columns['target']] ?? '';
            if ($source === '' || $target === '') {
                $errors[] = ['line' => $line, 'source' => $source, 'message' => 'Нужны минимум две колонки: исходный и целевой URL.'];
                continue;
            }

            $rows[] = [
                'line' => $line,
                'source' => $source,
                'target' => $target,
                'status' => isset($columns['status']) ? ($cells[$columns['status']] ?? '') : '',
                'active' => isset($columns['active']) ? ($cells[$columns['active']] ?? '') : '',
            ];
        }

        if ($rows === [] && $errors === []) {
            throw new InvalidArgumentException('В файле нет ни одной строки с редиректом.');
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param list<string> $lines
     */
    private function detectDelimiter(array $lines): string
    {
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $best = ',';
            $bestCount = 0;
            foreach ([',', ';', "\t"] as $candidate) {
                $count = substr_count($line, $candidate);
                if ($count > $bestCount) {
                    $best = $candidate;
                    $bestCount = $count;
                }
            }

            return $best;
        }

        return ',';
    }

    /**
     * @param list<string> $cells
     *
     * @return array{source: int, target: int, status?: int, active?: int}|null
     */
    private function mapHeader(array $cells): ?array
    {
        $found = [];
        foreach ($cells as $position => $cell) {
            $name = mb_strtolower($cell);
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (\in_array($name, $aliases, true) && !isset($found[$field])) {
                    $found[$field] = $position;
                }
            }
        }

        if (!isset($found['source']) && !isset($found['target'])) {
            return null;
        }

        if (!isset($found['source']) || !isset($found['target'])) {
            throw new InvalidArgumentException('В заголовке CSV должны быть обе колонки: source (исходный URL) и target (целевой URL).');
        }

        return $found;
    }
}

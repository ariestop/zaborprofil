<?php

declare(strict_types=1);

namespace App\Module\Lead\Application\Export;

use App\Module\Lead\Domain\Entity\Lead;

final class LeadCsvExporter
{
    private const string BOM = "\xEF\xBB\xBF";

    /**
     * Минимальный набор полей: IP, User-Agent и снимок согласия в выгрузку не попадают.
     *
     * @var list<string>
     */
    public const array HEADER = ['id', 'created_at', 'name', 'phone', 'email', 'source', 'status', 'assignee', 'spam_score', 'message'];

    /**
     * @param iterable<Lead> $leads
     * @param array<string, string> $assigneeLabels email ответственного по его идентификатору
     */
    public function export(iterable $leads, array $assigneeLabels = []): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, self::BOM);
        $this->writeRow($handle, self::HEADER);
        foreach ($leads as $lead) {
            $row = $lead->toArray();
            $assigneeId = \is_string($row['assigneeId']) ? $row['assigneeId'] : null;

            $this->writeRow($handle, [
                self::text($row['id']),
                self::text($row['createdAt']),
                self::text($row['name']),
                self::text($row['phone']),
                self::text($row['email']),
                self::text($row['source']),
                self::text($row['status']),
                $assigneeId === null ? '' : ($assigneeLabels[$assigneeId] ?? $assigneeId),
                self::text($row['spamScore']),
                self::text($row['message']),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * Защита от CSV-инъекций: ячейка, которую Excel/LibreOffice прочтёт как формулу, получает ведущий апостроф.
     * Номера телефонов вида «+7 (999) 123-45-67» формулой не являются и остаются без изменений.
     */
    public static function escapeCell(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\+[\d\s().\-]+$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1 || preg_match('/^[\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param resource $handle
     * @param list<string> $cells
     */
    private function writeRow($handle, array $cells): void
    {
        fputcsv($handle, array_map(self::escapeCell(...), $cells), ',', '"', '', "\n");
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}

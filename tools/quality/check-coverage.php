<?php

declare(strict_types=1);

/**
 * Проверяет порог покрытия строк по Clover-отчёту PHPUnit.
 *
 * Использование: php tools/quality/check-coverage.php <clover.xml> <минимальный процент>
 */

$cloverPath = $argv[1] ?? '';
$threshold = isset($argv[2]) ? (float) $argv[2] : 0.0;

if ($cloverPath === '' || !is_file($cloverPath)) {
    fwrite(STDERR, sprintf("Clover-отчёт не найден: %s\n", $cloverPath));
    exit(2);
}

$xml = simplexml_load_file($cloverPath);
if ($xml === false) {
    fwrite(STDERR, "Не удалось разобрать Clover-отчёт.\n");
    exit(2);
}

$metrics = $xml->project->metrics ?? null;
$statements = $metrics !== null ? (int) $metrics['statements'] : 0;
$covered = $metrics !== null ? (int) $metrics['coveredstatements'] : 0;

if ($statements === 0) {
    fwrite(STDERR, "В отчёте нет данных о покрытии (включён ли pcov или xdebug?).\n");
    exit(2);
}

$percent = $covered / $statements * 100;
printf("Покрытие строк: %.2f%% (%d из %d), порог: %.2f%%\n", $percent, $covered, $statements, $threshold);

if ($percent < $threshold) {
    fwrite(STDERR, "Покрытие ниже порога.\n");
    exit(1);
}

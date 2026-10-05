<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;
use InvalidArgumentException;

final readonly class RedirectImporter
{
    private const int DEFAULT_STATUS = 301;

    public function __construct(
        private RedirectCsvParser $parser,
        private RedirectRuleValidator $validator,
        private RedirectRepositoryInterface $redirects,
        private PublicPageCacheInvalidator $publicPageCache,
    ) {
    }

    /**
     * Построчная валидация CSV. Корректные строки применяются, ошибочные пропускаются и попадают в отчёт;
     * при `$dryRun` ничего не записывается.
     */
    public function import(string $csv, bool $dryRun, bool $updateExisting): RedirectImportReport
    {
        $parsed = $this->parser->parse($csv);
        $report = new RedirectImportReport($dryRun, \count($parsed['rows']) + \count($parsed['errors']));
        foreach ($parsed['errors'] as $error) {
            $report->addError($error['line'], $error['source'], $error['message']);
        }

        $existing = [];
        $graph = new RedirectGraph();
        foreach ($this->redirects->findAllOrdered() as $redirect) {
            $existing[self::key($redirect->sourcePath())] = $redirect;
            if ($redirect->isActive()) {
                $internal = $this->validator->internalPath($redirect->targetPath());
                if ($internal !== null) {
                    $graph->set($redirect->sourcePath(), $internal);
                }
            }
        }

        $seen = [];
        $toSave = [];

        foreach ($parsed['rows'] as $row) {
            try {
                $status = $this->parseStatus($row['status']);
                $active = $this->parseActive($row['active']);
                $rule = $this->validator->validate($row['source'], $row['target'], $status);
                $key = self::key($rule->sourcePath);

                if (isset($seen[$key])) {
                    throw new RedirectValidationException(\sprintf('Дубль исходного URL: уже встречался в строке %d.', $seen[$key]), 'sourcePath', RedirectValidationException::CODE_DUPLICATE);
                }
                $seen[$key] = $row['line'];

                $current = $existing[$key] ?? null;
                if ($current !== null && !$updateExisting) {
                    ++$report->skipped;
                    $report->addPreview($row['line'], $rule->sourcePath, $rule->targetPath, $rule->statusCode, 'skip');
                    continue;
                }

                $internalTarget = $this->validator->internalPath($rule->targetPath);
                if ($active && $internalTarget !== null) {
                    $loop = $graph->loopFor($rule->sourcePath, $internalTarget);
                    if ($loop !== null) {
                        throw new RedirectValidationException('Редирект создаёт цикл: '.implode(' → ', $loop).'.', 'targetPath', RedirectValidationException::CODE_LOOP);
                    }

                    $chain = $graph->chainFor($rule->sourcePath, $internalTarget);
                    if (\count($chain) > 2) {
                        $report->addWarning($row['line'], $rule->sourcePath, 'Цепочка редиректов: '.implode(' → ', $chain).'.');
                    }
                }

                $graph->set($rule->sourcePath, $active ? $internalTarget : null);

                if ($current !== null) {
                    if (!$dryRun) {
                        $current->update($rule->targetPath, $rule->statusCode, $active);
                        $toSave[] = $current;
                    }
                    ++$report->updated;
                    $report->addPreview($row['line'], $rule->sourcePath, $rule->targetPath, $rule->statusCode, 'update');
                } else {
                    $created = new Redirect($rule->sourcePath, $rule->targetPath, $rule->statusCode, $active);
                    $existing[$key] = $created;
                    if (!$dryRun) {
                        $toSave[] = $created;
                    }
                    ++$report->created;
                    $report->addPreview($row['line'], $rule->sourcePath, $rule->targetPath, $rule->statusCode, 'create');
                }
            } catch (InvalidArgumentException $exception) {
                $report->addError($row['line'], $row['source'], $exception->getMessage());
            }
        }

        if ($toSave !== []) {
            $this->redirects->saveAll($toSave);
            $this->publicPageCache->invalidateAll();
        }

        return $report;
    }

    private function parseStatus(string $value): int
    {
        if ($value === '') {
            return self::DEFAULT_STATUS;
        }

        if (preg_match('/^\d{3}$/', $value) !== 1) {
            throw new RedirectValidationException('Код ответа должен быть числом: 301, 302, 307 или 308.', 'statusCode');
        }

        return (int) $value;
    }

    private function parseActive(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        return match (mb_strtolower($value)) {
            '1', 'true', 'yes', 'y', 'on', 'да' => true,
            '0', 'false', 'no', 'n', 'off', 'нет' => false,
            default => throw new RedirectValidationException('Колонка active должна содержать 1/0, true/false или да/нет.', 'active'),
        };
    }

    private static function key(string $sourcePath): string
    {
        return mb_strtolower($sourcePath);
    }
}

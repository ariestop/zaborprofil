<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Redirect;

final class RedirectImportReport
{
    public const int MAX_PREVIEW_ROWS = 100;

    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /**
     * @var list<array{line: int, source: string, message: string}>
     */
    private array $errors = [];

    /**
     * @var list<array{line: int, source: string, message: string}>
     */
    private array $warnings = [];

    /**
     * @var list<array{line: int, source: string, target: string, status: int, action: string}>
     */
    private array $preview = [];

    public function __construct(public readonly bool $dryRun, public int $totalRows = 0)
    {
    }

    public function addError(int $line, string $source, string $message): void
    {
        $this->errors[] = ['line' => $line, 'source' => $source, 'message' => $message];
    }

    public function addWarning(int $line, string $source, string $message): void
    {
        $this->warnings[] = ['line' => $line, 'source' => $source, 'message' => $message];
    }

    public function addPreview(int $line, string $source, string $target, int $status, string $action): void
    {
        if (\count($this->preview) < self::MAX_PREVIEW_ROWS) {
            $this->preview[] = ['line' => $line, 'source' => $source, 'target' => $target, 'status' => $status, 'action' => $action];
        }
    }

    public function failed(): int
    {
        return \count($this->errors);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dryRun' => $this->dryRun,
            'totalRows' => $this->totalRows,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->failed(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'preview' => $this->preview,
        ];
    }
}

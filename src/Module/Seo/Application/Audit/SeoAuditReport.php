<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Audit;

use App\Module\Content\Domain\Entity\Page;

final readonly class SeoAuditReport
{
    /**
     * @param list<SeoAuditIssue> $issues
     */
    public function __construct(
        public string $pageId,
        public string $path,
        public array $issues,
    ) {
    }

    /**
     * @param list<SeoAuditIssue> $issues
     */
    public static function forPage(Page $page, array $issues): self
    {
        return new self((string) $page->id(), $page->path(), $issues);
    }

    public function passed(): bool
    {
        return !$this->hasBlockingIssues();
    }

    public function hasBlockingIssues(): bool
    {
        return array_any($this->issues, fn (SeoAuditIssue $issue): bool => $issue->severity === SeoAuditSeverity::P0 || $issue->severity === SeoAuditSeverity::P1);
    }

    public function blockingSummary(): string
    {
        $codes = [];
        foreach ($this->issues as $issue) {
            if ($issue->severity === SeoAuditSeverity::P0 || $issue->severity === SeoAuditSeverity::P1) {
                $codes[] = $issue->code;
            }
        }

        return implode(', ', $codes);
    }

    /**
     * @return array{pageId: string, path: string, passed: bool, issues: list<array{severity: string, code: string, message: string, field: string}>}
     */
    public function toArray(): array
    {
        return [
            'pageId' => $this->pageId,
            'path' => $this->path,
            'passed' => $this->passed(),
            'issues' => array_map(static fn (SeoAuditIssue $issue): array => $issue->toArray(), $this->issues),
        ];
    }
}

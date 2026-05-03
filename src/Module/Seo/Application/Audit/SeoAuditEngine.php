<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Audit;

use App\Module\Content\Domain\Entity\Page;

final readonly class SeoAuditEngine
{
    /**
     * @var list<string>
     */
    private const array RESERVED_PATH_PREFIXES = [
        '/admin',
        '/api',
        '/dev',
        '/_profiler',
        '/_wdt',
        '/_fragment',
        '/build',
        '/uploads',
        '/health',
    ];

    public function auditPage(Page $page): SeoAuditReport
    {
        $issues = [
            ...$this->pathIssues($page),
            ...$this->metadataIssues($page),
            ...$this->contentIssues($page),
        ];

        return SeoAuditReport::forPage($page, $issues);
    }

    /**
     * @return list<SeoAuditIssue>
     */
    private function pathIssues(Page $page): array
    {
        $issues = [];
        foreach (self::RESERVED_PATH_PREFIXES as $prefix) {
            if ($page->path() === $prefix || str_starts_with($page->path(), $prefix.'/')) {
                $issues[] = new SeoAuditIssue(
                    SeoAuditSeverity::P0,
                    'seo.path.reserved_prefix',
                    \sprintf('Page path cannot use reserved prefix "%s".', $prefix),
                    'path',
                );
            }
        }

        return $issues;
    }

    /**
     * @return list<SeoAuditIssue>
     */
    private function metadataIssues(Page $page): array
    {
        $issues = [];

        if (mb_strlen($page->title()) < 10) {
            $issues[] = new SeoAuditIssue(SeoAuditSeverity::P2, 'seo.title.too_short', 'Page title should be at least 10 characters.', 'title');
        }

        if ($page->metaDescription() === null) {
            $issues[] = new SeoAuditIssue(SeoAuditSeverity::P2, 'seo.meta_description.missing', 'Meta description is recommended before publication.', 'metaDescription');
        } elseif (mb_strlen($page->metaDescription()) < 80) {
            $issues[] = new SeoAuditIssue(SeoAuditSeverity::P2, 'seo.meta_description.too_short', 'Meta description should be at least 80 characters.', 'metaDescription');
        }

        if (!$page->isIndexable()) {
            $issues[] = new SeoAuditIssue(SeoAuditSeverity::P2, 'seo.robots.noindex', 'Page is marked as noindex and will be excluded from sitemap.', 'isIndexable');
        }

        if ($page->ogTitle() === null) {
            $issues[] = new SeoAuditIssue(SeoAuditSeverity::P2, 'seo.og_title.missing', 'OpenGraph title is recommended for social previews.', 'ogTitle');
        }

        if ($page->ogImage() === null) {
            $issues[] = new SeoAuditIssue(SeoAuditSeverity::P2, 'seo.og_image.missing', 'OpenGraph image is recommended for social previews.', 'ogImage');
        }

        return $issues;
    }

    /**
     * @return list<SeoAuditIssue>
     */
    private function contentIssues(Page $page): array
    {
        if ($page->enabledBlocks() === []) {
            return [
                new SeoAuditIssue(SeoAuditSeverity::P2, 'seo.content.empty', 'Published page should contain at least one enabled content block.', 'blocks'),
            ];
        }

        return [];
    }
}

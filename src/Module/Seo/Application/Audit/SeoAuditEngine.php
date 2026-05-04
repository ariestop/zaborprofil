<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Audit;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Enum\BlockType;

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
        $issues = [];
        $enabledBlocks = $page->enabledBlocks();
        if ($enabledBlocks === []) {
            $issues[] = new SeoAuditIssue(SeoAuditSeverity::P2, 'seo.content.empty', 'Published page should contain at least one enabled content block.', 'blocks');
        }

        if ($page->type()->isCommercial() && !$this->hasBlock($enabledBlocks, BlockType::CtaForm)) {
            $issues[] = new SeoAuditIssue(SeoAuditSeverity::P2, 'seo.content.cta_missing', 'Commercial pages should contain a CTA form block.', 'blocks');
        }

        if (\in_array($page->type()->value, ['landing', 'service', 'seo_landing'], true) && !$this->hasBlock($enabledBlocks, BlockType::SeoText)) {
            $issues[] = new SeoAuditIssue(SeoAuditSeverity::P2, 'seo.content.seo_text_missing', 'Landing and service pages should contain an SEO text block.', 'blocks');
        }

        foreach ($enabledBlocks as $block) {
            $content = $block->content();
            if ($block->type() === BlockType::Faq && !$this->hasCompleteFaqItem($content['items'] ?? [])) {
                $issues[] = new SeoAuditIssue(SeoAuditSeverity::P1, 'seo.content.faq_empty', 'FAQ block must contain at least one question and answer.', 'blocks');
            }

            $html = $content['html'] ?? '';
            if ($block->type() === BlockType::HtmlEmbed && \is_string($html) && preg_match('/<\s*script\b/i', $html) === 1) {
                $issues[] = new SeoAuditIssue(SeoAuditSeverity::P1, 'seo.security.html_embed_script', 'HTML embed cannot contain script tags.', 'blocks');
            }
        }

        return $issues;
    }

    /**
     * @param list<PageBlock> $blocks
     */
    private function hasBlock(array $blocks, BlockType $type): bool
    {
        return array_any($blocks, static fn (PageBlock $block): bool => $block->type() === $type);
    }

    /**
     * @param mixed $items
     */
    private function hasCompleteFaqItem(mixed $items): bool
    {
        if (!\is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $question = $item['question'] ?? $item['title'] ?? '';
            $answer = $item['answer'] ?? '';

            if (\is_string($question) && trim($question) !== '' && \is_string($answer) && trim($answer) !== '') {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Audit;

use App\Module\Content\Domain\Entity\Page;
use InvalidArgumentException;

final readonly class PrePublishChecklist
{
    public function __construct(private SeoAuditEngine $audit)
    {
    }

    public function assertPublishable(Page $page): SeoAuditReport
    {
        $report = $this->audit->auditPage($page);
        if ($report->hasBlockingIssues()) {
            throw new InvalidArgumentException('Pre-publish SEO checklist failed: '.$report->blockingSummary());
        }

        return $report;
    }
}

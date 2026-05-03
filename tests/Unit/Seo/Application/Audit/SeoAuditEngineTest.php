<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo\Application\Audit;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Seo\Application\Audit\PrePublishChecklist;
use App\Module\Seo\Application\Audit\SeoAuditEngine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SeoAuditEngineTest extends TestCase
{
    public function testReservedPathIsBlockingIssue(): void
    {
        $page = new Page(PageType::Landing, 'Admin Landing', 'admin-landing', '/admin/landing/', 'Admin Landing');
        $report = new SeoAuditEngine()->auditPage($page);

        self::assertFalse($report->passed());
        self::assertTrue($report->hasBlockingIssues());
        self::assertSame('seo.path.reserved_prefix', $report->issues[0]->code);
    }

    public function testMissingMetaDescriptionIsWarningOnly(): void
    {
        $page = new Page(PageType::Landing, 'Заборы под ключ', 'zabory', '/zabory/', 'Заборы под ключ');
        $report = new SeoAuditEngine()->auditPage($page);

        self::assertTrue($report->passed());
        self::assertFalse($report->hasBlockingIssues());
        self::assertNotEmpty($report->issues);
    }

    public function testPrePublishChecklistBlocksP0AndP1Issues(): void
    {
        $page = new Page(PageType::Landing, 'Admin Landing', 'admin-landing', '/admin/landing/', 'Admin Landing');
        $checklist = new PrePublishChecklist(new SeoAuditEngine());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pre-publish SEO checklist failed: seo.path.reserved_prefix');

        $checklist->assertPublishable($page);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Service\PageStatusTransitionPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageStatusTransitionPolicyTest extends TestCase
{
    public function testEditorCanSubmitDraftForReview(): void
    {
        $policy = new PageStatusTransitionPolicy();

        $policy->assertAllowed(PageStatus::Draft, PageStatus::Review, ['ROLE_EDITOR']);

        self::assertSame(['review', 'approved', 'published', 'archived', 'deleted'], $policy->nextStatusValues(PageStatus::Draft));
    }

    public function testEditorCannotPublishDirectly(): void
    {
        $policy = new PageStatusTransitionPolicy();

        $this->expectException(InvalidArgumentException::class);

        $policy->assertAllowed(PageStatus::Draft, PageStatus::Published, ['ROLE_EDITOR']);
    }

    public function testAdminCanUnpublishPublishedPage(): void
    {
        $policy = new PageStatusTransitionPolicy();

        $policy->assertAllowed(PageStatus::Published, PageStatus::Unpublished, ['ROLE_ADMIN']);

        self::addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{PageStatus}>
     */
    public static function unpublishedWorkStatuses(): iterable
    {
        yield 'черновик' => [PageStatus::Draft];
        yield 'на проверке' => [PageStatus::Review];
        yield 'одобрено' => [PageStatus::Approved];
    }

    #[DataProvider('unpublishedWorkStatuses')]
    public function testUnpublishedWorkCanBeArchived(PageStatus $from): void
    {
        $policy = new PageStatusTransitionPolicy();

        $policy->assertAllowed($from, PageStatus::Archived, ['ROLE_ADMIN']);

        self::assertContains('archived', $policy->nextStatusValues($from));
    }

    public function testScheduledPageMustBeUnscheduledBeforeArchiving(): void
    {
        $policy = new PageStatusTransitionPolicy();

        $this->expectException(InvalidArgumentException::class);

        $policy->assertAllowed(PageStatus::Scheduled, PageStatus::Archived, ['ROLE_ADMIN']);
    }
}

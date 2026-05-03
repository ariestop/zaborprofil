<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Service\PageStatusTransitionPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PageStatusTransitionPolicyTest extends TestCase
{
    public function testEditorCanSubmitDraftForReview(): void
    {
        $policy = new PageStatusTransitionPolicy();

        $policy->assertAllowed(PageStatus::Draft, PageStatus::Review, ['ROLE_EDITOR']);

        self::assertSame(['review', 'approved', 'published', 'deleted'], $policy->nextStatusValues(PageStatus::Draft));
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
}

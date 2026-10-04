<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Enum\PageType;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PageSchedulingTest extends TestCase
{
    public function testScheduleStoresBothDatesAndCancelReturnsPageToApproved(): void
    {
        $page = $this->page();
        $publishAt = new DateTimeImmutable('+1 day');
        $unpublishAt = new DateTimeImmutable('+2 days');

        $page->schedule($publishAt, $unpublishAt);

        self::assertSame(PageStatus::Scheduled, $page->status());
        self::assertSame($publishAt, $page->scheduledPublishAt());
        self::assertSame($unpublishAt, $page->scheduledUnpublishAt());

        $page->cancelSchedule();

        self::assertSame(PageStatus::Approved, $page->status());
        self::assertNull($page->scheduledPublishAt());
        self::assertNull($page->scheduledUnpublishAt());
    }

    public function testScheduleRejectsUnpublishNotAfterPublish(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $at = new DateTimeImmutable('+1 day');
        $this->page()->schedule($at, $at);
    }

    public function testPublishedPageCanScheduleAndCancelUnpublishWithoutLeavingPublishedStatus(): void
    {
        $page = $this->page();
        $page->publish();
        $unpublishAt = new DateTimeImmutable('+3 days');

        $page->scheduleUnpublish($unpublishAt);
        self::assertSame(PageStatus::Published, $page->status());
        self::assertSame($unpublishAt, $page->scheduledUnpublishAt());

        $page->cancelSchedule();
        self::assertSame(PageStatus::Published, $page->status());
        self::assertNull($page->scheduledUnpublishAt());
    }

    public function testScheduleUnpublishRequiresPublishedPage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->page()->scheduleUnpublish(new DateTimeImmutable('+1 day'));
    }

    public function testCancelScheduleWithoutScheduleIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $page = $this->page();
        $page->publish();
        $page->cancelSchedule();
    }

    public function testPublishKeepsUnpublishDateOfScheduledWindow(): void
    {
        $page = $this->page();
        $unpublishAt = new DateTimeImmutable('+2 days');
        $page->schedule(new DateTimeImmutable('+1 day'), $unpublishAt);

        $page->publish();

        self::assertNull($page->scheduledPublishAt());
        self::assertSame($unpublishAt, $page->scheduledUnpublishAt());
    }

    public function testLeavingScheduledOrPublishedStateClearsSchedule(): void
    {
        $page = $this->page();
        $page->schedule(new DateTimeImmutable('+1 day'), new DateTimeImmutable('+2 days'));
        $page->approve();
        self::assertNull($page->scheduledPublishAt());
        self::assertNull($page->scheduledUnpublishAt());

        $page->publish();
        $page->scheduleUnpublish(new DateTimeImmutable('+1 day'));
        $page->unpublish();
        self::assertNull($page->scheduledUnpublishAt());

        $page->publish();
        $page->scheduleUnpublish(new DateTimeImmutable('+1 day'));
        $page->archive();
        self::assertNull($page->scheduledUnpublishAt());
    }

    private function page(): Page
    {
        return new Page(PageType::Landing, 'Заборы', 'zabory', '/zabory/', 'Заборы');
    }
}

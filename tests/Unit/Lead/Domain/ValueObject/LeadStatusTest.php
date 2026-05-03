<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lead\Domain\ValueObject;

use App\Module\Lead\Domain\ValueObject\LeadStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LeadStatusTest extends TestCase
{
    public function testNormalizesSupportedStatus(): void
    {
        self::assertSame(LeadStatus::IN_PROGRESS, LeadStatus::normalize(' In_Progress '));
    }

    public function testRejectsUnsupportedStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LeadStatus::normalize('closed');
    }
}

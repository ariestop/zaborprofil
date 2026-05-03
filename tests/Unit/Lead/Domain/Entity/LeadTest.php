<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lead\Domain\Entity;

use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\ValueObject\LeadStatus;
use PHPUnit\Framework\TestCase;

final class LeadTest extends TestCase
{
    public function testCanMarkLeadAsSpamWithReasons(): void
    {
        $lead = new Lead('public_form', 'Иван', '+79990000000', null, null, ['consent' => true]);

        $lead->markSpam(120, ['honeypot_filled']);
        $payload = $lead->toArray();

        self::assertSame(LeadStatus::SPAM, $payload['status']);
        self::assertSame(120, $payload['spamScore']);
        self::assertSame(['honeypot_filled'], $payload['spamReasons']);
    }
}

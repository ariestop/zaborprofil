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

    public function testStoresPageUrlUtmAndDetectsCompanyLeads(): void
    {
        $lead = new Lead(
            'public_page_form',
            'ООО «СтройДвор»',
            '+79990000000',
            null,
            null,
            ['consent' => true],
            'https://zaborprofil.ru/zabory/proflist?utm_source=yandex',
            ['source' => 'yandex', 'medium' => 'cpc', 'campaign' => " zabor\n", 'unknown' => 'x', 'term' => 5],
        );
        $payload = $lead->toArray();

        self::assertSame('https://zaborprofil.ru/zabory/proflist?utm_source=yandex', $payload['pageUrl']);
        self::assertSame(['source' => 'yandex', 'medium' => 'cpc', 'campaign' => 'zabor'], $payload['utm']);
        self::assertTrue($payload['b2b']);
        self::assertNull($payload['readAt']);

        $lead->markRead();
        self::assertNotNull($lead->toArray()['readAt']);

        $person = new Lead('public_form', 'Андрей Смирнов', '+79990000001', null, null, ['consent' => true]);
        self::assertFalse($person->toArray()['b2b']);
        self::assertSame([], $person->toArray()['utm']);
    }
}

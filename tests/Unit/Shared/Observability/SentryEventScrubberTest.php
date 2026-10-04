<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Observability;

use App\Shared\Infrastructure\Observability\SentryEventScrubber;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\UserDataBag;

final class SentryEventScrubberTest extends TestCase
{
    public function testRemovesPersonalDataFromEvent(): void
    {
        $event = Event::createEvent();
        $event->setMessage('Lead from user@example.test +7 999 123-45-67');
        $event->setRequest([
            'url' => 'https://zaborprofil.test/api/leads?phone=79991234567',
            'method' => 'POST',
            'cookies' => ['PHPSESSID' => 'abc'],
            'headers' => ['Authorization' => 'Bearer x'],
            'data' => ['phone' => '+79991234567'],
            'query_string' => 'phone=79991234567',
        ]);
        $user = new UserDataBag('42', 'admin@example.test', '10.0.0.1', 'admin');
        $event->setUser($user);
        $event->setServerName('host-1');
        $event->setBreadcrumb([new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'sql', 'SELECT * FROM leads WHERE phone = 7999')]);
        $event->setExtra(['phone' => '+79991234567', 'leadId' => 'abc', 'note' => 'call 192.168.0.5']);
        $event->setExceptions([new ExceptionDataBag(new \RuntimeException('Duplicate user@example.test'))]);

        $scrubbed = (new SentryEventScrubber())($event);

        self::assertSame('Lead from [email] [phone]', $scrubbed->getMessage());
        self::assertEquals(['url' => 'https://zaborprofil.test/api/leads', 'method' => 'POST'], $scrubbed->getRequest());
        self::assertSame('42', $scrubbed->getUser()?->getId());
        self::assertNull($scrubbed->getUser()->getEmail());
        self::assertNull($scrubbed->getUser()->getIpAddress());
        self::assertNull($scrubbed->getServerName());
        self::assertSame([], $scrubbed->getBreadcrumbs());
        self::assertSame(['phone' => '[redacted]', 'leadId' => 'abc', 'note' => 'call [ip]'], $scrubbed->getExtra());
        self::assertSame('Duplicate [email]', $scrubbed->getExceptions()[0]->getValue());
    }

    public function testDropsUserWithoutIdentifier(): void
    {
        $event = Event::createEvent();
        $event->setUser(new UserDataBag(null, 'a@example.test', '10.0.0.1'));

        self::assertNull((new SentryEventScrubber())($event)->getUser());
    }
}

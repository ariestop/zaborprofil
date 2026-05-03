<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lead\Application\AntiSpam;

use App\Module\Lead\Application\AntiSpam\LeadAntiSpamChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

final class LeadAntiSpamCheckerTest extends TestCase
{
    public function testHoneypotMarksLeadAsSpam(): void
    {
        $result = (new LeadAntiSpamChecker(new ArrayAdapter()))->check([
            'website' => 'https://spam.example',
        ], Request::create('/api/leads', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']));

        self::assertTrue($result->isSpam());
        self::assertContains('honeypot_filled', $result->reasons);
    }

    public function testRateLimitAddsSpamReasonAfterRepeatedRequests(): void
    {
        $checker = new LeadAntiSpamChecker(new ArrayAdapter());
        $request = Request::create('/api/leads', 'POST', server: ['REMOTE_ADDR' => '127.0.0.2']);
        $result = $checker->check([], $request);

        for ($i = 0; $i < 5; ++$i) {
            $result = $checker->check([], $request);
        }

        self::assertContains('rate_limit_exceeded', $result->reasons);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityHeadersTest extends WebTestCase
{
    public function testPublicResponseContainsSecurityHeaders(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
        self::assertNotNull($client->getResponse()->headers->get('Permissions-Policy'));

        // В test, как в prod и staging, CSP применяется (enforce), а не только сообщает о нарушениях.
        $policy = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringContainsString("script-src 'self';", $policy);
        self::assertStringContainsString("object-src 'none'", $policy);
        self::assertFalse($client->getResponse()->headers->has('Content-Security-Policy-Report-Only'));
    }
}

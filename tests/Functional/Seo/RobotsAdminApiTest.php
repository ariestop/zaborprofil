<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use App\Tests\Support\Admin\AdminApiTestCase;

final class RobotsAdminApiTest extends AdminApiTestCase
{
    public function testShowReturnsDefaultBodyAndEnvironment(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'GET', '/admin/api/seo/robots');

        self::assertResponseIsSuccessful();
        $payload = $this->json($client);
        self::assertSame('', $payload['body']);
        self::assertSame('test', $payload['environment']);
        self::assertTrue($payload['overriddenByEnvironment']);
        self::assertStringContainsString('Disallow: /admin/', $this->text($payload['defaultBody']));
        self::assertSame("User-agent: *\nDisallow: /\n", $payload['effectiveBody']);
    }

    public function testPreviewValidatesWithoutSaving(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'POST', '/admin/api/seo/robots/preview', ['body' => "User-agent: *\r\nDisallow: private\r\n"]);

        self::assertResponseIsSuccessful();
        $preview = $this->json($client);
        self::assertFalse($preview['valid']);
        $issues = $this->rows($preview['issues']);
        self::assertSame('error', $issues[0]['severity']);
        self::assertSame(2, $issues[0]['line']);

        $this->api($client, 'GET', '/admin/api/seo/robots');
        self::assertSame('', $this->json($client)['body']);
    }

    public function testPreviewOfEmptyBodyUsesDefault(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'POST', '/admin/api/seo/robots/preview', ['body' => '  ']);

        self::assertResponseIsSuccessful();
        $preview = $this->json($client);
        self::assertTrue($preview['usesDefault']);
        self::assertNull($preview['normalizedBody']);
        self::assertTrue($preview['valid']);
    }

    public function testPreviewReportsTooLongBody(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'POST', '/admin/api/seo/robots/preview', ['body' => "User-agent: *\n".str_repeat("# filler\n", 1500)]);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->json($client)['valid']);
    }

    public function testSaveRejectsSyntaxErrorsWithDetails(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'PUT', '/admin/api/seo/robots', ['body' => "Disallow: /x/\n"]);

        self::assertResponseStatusCodeSame(422);
        $payload = $this->json($client);
        self::assertSame('VALIDATION', $payload['code']);
        $details = $this->rows($payload['details']);
        self::assertSame('body', $details[0]['field']);
        self::assertStringContainsString('Строка 1', $this->text($details[0]['message']));

        $this->api($client, 'GET', '/admin/api/seo/robots');
        self::assertSame('', $this->json($client)['body']);
    }

    public function testSaveAndResetToDefault(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'PUT', '/admin/api/seo/robots', ['body' => "User-agent: *\nDisallow: /admin/\nSitemap: https://zaborprofil.test/sitemap.xml"]);
        self::assertResponseIsSuccessful();
        self::assertSame("User-agent: *\nDisallow: /admin/\nSitemap: https://zaborprofil.test/sitemap.xml\n", $this->json($client)['body']);

        $this->api($client, 'PUT', '/admin/api/seo/robots', ['body' => null]);
        self::assertResponseIsSuccessful();
        self::assertSame('', $this->json($client)['body']);
    }
}

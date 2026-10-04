<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Tests\Support\Admin\AdminApiTestCase;

final class ClientErrorApiTest extends AdminApiTestCase
{
    public function testAdminCanReportClientError(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'POST', '/admin/api/client-errors', [
            'message' => 'TypeError: x is undefined',
            'source' => 'error-boundary',
            'url' => 'https://zaborprofil.test/admin/pages?token=secret',
            'stack' => 'at render (app.js:1:1)',
        ]);

        self::assertResponseStatusCodeSame(202);
        self::assertSame('accepted', $this->json($client)['status'] ?? null);
    }

    public function testInvalidReportIsRejected(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'POST', '/admin/api/client-errors', ['source' => 'window-error']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION', $this->json($client)['code'] ?? null);
    }

    public function testAnonymousUserCannotReport(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', '/admin/api/client-errors', ['message' => 'boom'], ['HTTP_ORIGIN' => 'https://zaborprofil.test']);

        self::assertContains($client->getResponse()->getStatusCode(), [302, 401, 403]);
    }

    public function testReportsAreRateLimitedPerUser(): void
    {
        $client = $this->adminClient();
        $client->disableReboot();

        for ($index = 0; $index < 20; ++$index) {
            $this->api($client, 'POST', '/admin/api/client-errors', ['message' => 'boom '.$index]);
            self::assertResponseStatusCodeSame(202);
        }

        $this->api($client, 'POST', '/admin/api/client-errors', ['message' => 'one more']);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('TOO_MANY_REQUESTS', $this->json($client)['code'] ?? null);
    }
}

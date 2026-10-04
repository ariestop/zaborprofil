<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Shared\Infrastructure\Observability\ServerErrorCounter;
use App\Tests\Support\Admin\AdminApiTestCase;

final class SystemObservabilityApiTest extends AdminApiTestCase
{
    public function testAdminReadsObservabilitySnapshot(): void
    {
        $client = $this->adminClient();

        $this->api($client, 'GET', '/admin/api/system/observability');

        self::assertResponseIsSuccessful();
        $payload = $this->json($client);
        $errors = $payload['serverErrors'] ?? null;
        self::assertIsArray($errors);
        self::assertIsInt($errors['lastHour'] ?? null);
        self::assertIsInt($errors['last24Hours'] ?? null);
        $queue = $payload['queue'] ?? null;
        self::assertIsArray($queue);
        self::assertIsInt($queue['pending'] ?? null);
        self::assertIsInt($queue['failed'] ?? null);
        $disk = $payload['disk'] ?? null;
        self::assertIsArray($disk);
        self::assertContains($disk['status'] ?? null, ['ok', 'warning', 'fail']);
        self::assertIsInt($disk['freeBytes'] ?? null);
        self::assertIsString($payload['checkedAt'] ?? null);
    }

    public function testSnapshotReflectsRecordedServerErrors(): void
    {
        $client = $this->adminClient();
        $client->disableReboot();
        $counter = self::getContainer()->get(ServerErrorCounter::class);
        self::assertInstanceOf(ServerErrorCounter::class, $counter);
        $before = $counter->countLastHours(24);
        $counter->increment();
        $counter->increment();

        $this->api($client, 'GET', '/admin/api/system/observability');

        self::assertResponseIsSuccessful();
        $errors = $this->json($client)['serverErrors'] ?? null;
        self::assertIsArray($errors);
        self::assertSame($before + 2, $errors['last24Hours'] ?? null);
    }

    public function testAnonymousUserCannotReadSnapshot(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/api/system/observability');

        self::assertResponseRedirects('/admin/login');
    }
}

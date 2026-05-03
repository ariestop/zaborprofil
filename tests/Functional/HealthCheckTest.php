<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthCheckTest extends WebTestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertJson($client->getResponse()->getContent() ?: '');

        $payload = json_decode($client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        /** @var array{status: string, app: string, database: string, redis: string, storage: string, checks: list<array<string, mixed>>} $payload */
        self::assertSame('ok', $payload['status']);
        self::assertSame('ok', $payload['app']);
        self::assertSame('ok', $payload['database']);
        self::assertSame('ok', $payload['redis']);
        self::assertSame('ok', $payload['storage']);
        self::assertNotEmpty($payload['checks']);
    }

    public function testLiveEndpointReturnsOk(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health/live');

        self::assertResponseIsSuccessful();
        self::assertJson($client->getResponse()->getContent() ?: '');
    }

    public function testReadyEndpointReturnsOk(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health/ready');

        self::assertResponseIsSuccessful();
        self::assertJson($client->getResponse()->getContent() ?: '');

        $payload = json_decode($client->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('ok', $payload['status']);
    }
}

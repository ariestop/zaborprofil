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

        /** @var array{status: string, app: string, database: string, redis: string, storage: string} $payload */
        self::assertSame('ok', $payload['status']);
        self::assertSame('ok', $payload['app']);
        self::assertSame('ok', $payload['database']);
        self::assertSame('ok', $payload['redis']);
        self::assertSame('ok', $payload['storage']);
    }
}

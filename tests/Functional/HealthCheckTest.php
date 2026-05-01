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
        self::assertStringContainsString('"status":"ok"', $client->getResponse()->getContent() ?: '');
    }
}

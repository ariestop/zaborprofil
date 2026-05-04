<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminNoIndexHeaderTest extends WebTestCase
{
    public function testAdminLoginResponseCarriesXRobotsTagHeader(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/login');

        self::assertResponseIsSuccessful();
        self::assertSame(
            'noindex, nofollow, noarchive',
            $client->getResponse()->headers->get('X-Robots-Tag'),
        );
    }

    public function testPublicResponseDoesNotCarryXRobotsTagHeader(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertNull($client->getResponse()->headers->get('X-Robots-Tag'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RobotsControllerTest extends WebTestCase
{
    public function testTestEnvironmentDisallowsEverything(): void
    {
        $client = self::createClient();
        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertSame('text/plain; charset=UTF-8', $client->getResponse()->headers->get('Content-Type'));
        self::assertSame("User-agent: *\nDisallow: /\n", (string) $client->getResponse()->getContent());
    }
}

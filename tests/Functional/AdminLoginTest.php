<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminLoginTest extends WebTestCase
{
    public function testLoginPageIsAvailable(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Вход в админ-панель');
    }

    public function testLoginPageIncludesViteSiteAssets(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/login');

        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        self::assertMatchesRegularExpression(
            '#<link rel="stylesheet" href="/build/assets/site-[^"]+\.css">#',
            $html,
            'Compiled site CSS must be linked from the public layout.',
        );
    }
}

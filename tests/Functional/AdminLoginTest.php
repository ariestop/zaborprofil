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
}

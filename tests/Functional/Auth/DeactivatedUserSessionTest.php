<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Module\User\Domain\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DeactivatedUserSessionTest extends WebTestCase
{
    public function testDeactivationEndsAnAlreadyOpenSession(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        SchemaTestHelper::recreateSchema($entityManager);
        $editor = new AdminUser('editor-leaving@example.test', 'hash', ['ROLE_EDITOR']);
        $entityManager->persist($editor);
        $entityManager->flush();
        $client->loginUser($editor);

        $client->request('GET', '/admin/api/me');
        self::assertResponseIsSuccessful('Пока учётная запись активна, сессия работает.');

        // Другой администратор отключает учётную запись, пока у пользователя открыта вкладка.
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $stored = $entityManager->getRepository(AdminUser::class)->findOneBy(['email' => 'editor-leaving@example.test']);
        self::assertInstanceOf(AdminUser::class, $stored);
        $stored->deactivate();
        $entityManager->flush();

        $client->request('GET', '/admin/api/me');
        self::assertResponseStatusCodeSame(401, 'Открытая сессия отключённого пользователя больше не пускает в API.');

        $client->request('GET', '/admin/dashboard');
        self::assertResponseRedirects();
        self::assertStringContainsString('/admin/login', (string) $client->getResponse()->headers->get('Location'));

        // Повторное включение не возвращает старую сессию: нужно войти заново.
        $stored = $entityManager->getRepository(AdminUser::class)->findOneBy(['email' => 'editor-leaving@example.test']);
        self::assertInstanceOf(AdminUser::class, $stored);
        $stored->activate();
        $entityManager->flush();
        $client->request('GET', '/admin/api/me');
        self::assertResponseStatusCodeSame(401);
    }
}

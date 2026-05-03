<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminSpaShellTest extends WebTestCase
{
    public function testAdminLoginPersistsAcrossRedirectToDashboard(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $this->createAdminUser('login@example.test', 'correct-password');

        $crawler = $client->request('GET', '/admin/login');
        $form = $crawler->selectButton('Войти')->form([
            '_username' => 'login@example.test',
            '_password' => 'correct-password',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/admin/dashboard');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#admin-app');
    }

    public function testAdminSpaRouteRendersShellForNestedAdminPage(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/admin/settings');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#admin-app');
        self::assertSelectorExists('meta[name="admin-csrf-token"]');
    }

    private function createAdminUser(string $email = 'spa@example.test', ?string $plainPassword = null): AdminUser
    {
        $entityManager = $this->entityManager();
        $passwordHash = 'hash';
        if ($plainPassword !== null) {
            $passwordHash = $this->passwordHasher()->hashPassword(
                new AdminUser($email, 'temporary-hash', ['ROLE_ADMIN']),
                $plainPassword,
            );
        }

        $user = new AdminUser($email, $passwordHash, ['ROLE_ADMIN']);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }

    private function passwordHasher(): UserPasswordHasherInterface
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new LogicException('User password hasher service is not available.');
        }

        return $passwordHasher;
    }
}

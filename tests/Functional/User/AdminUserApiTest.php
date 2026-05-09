<?php

declare(strict_types=1);

namespace App\Tests\Functional\User;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminUserApiTest extends WebTestCase
{
    public function testSuperAdminCanListAndUpdateUserRoles(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $superAdmin = $this->createAdminUser('super@example.test', ['ROLE_SUPER_ADMIN']);
        $target = $this->createAdminUser('editor@example.test', ['ROLE_EDITOR']);

        $client->loginUser($superAdmin);

        $client->request('GET', '/admin/api/users');
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertIsArray($payload['users'] ?? null);
        self::assertNotEmpty($payload['users']);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.(string) $target->id().'/roles', [
            'roles' => ['ROLE_ADMIN'],
        ]);
        self::assertResponseIsSuccessful();

        $updatedPayload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($updatedPayload);
        self::assertContains('ROLE_ADMIN', $updatedPayload['roles'] ?? []);
    }

    private function createAdminUser(string $email, array $roles): AdminUser
    {
        $entityManager = $this->entityManager();
        $user = new AdminUser($email, 'hash', $roles);
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

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequestWithCsrf(KernelBrowser $client, string $method, string $uri, array $payload = []): void
    {
        $client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        if (!preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', $html, $matches)) {
            throw new LogicException('Admin CSRF token meta tag was not rendered.');
        }

        $client->jsonRequest($method, $uri, $payload, [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $matches[1],
            'HTTP_ORIGIN' => 'http://zaborprofil.test',
            'HTTP_HOST' => 'zaborprofil.test',
        ]);
    }
}

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

    public function testAdminCanListUsers(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $admin = $this->createAdminUser('admin@example.test', ['ROLE_ADMIN']);
        $this->createAdminUser('editor@example.test', ['ROLE_EDITOR']);

        $client->loginUser($admin);

        $client->request('GET', '/admin/api/users');
        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertIsArray($payload['users'] ?? null);
        self::assertNotEmpty($payload['users']);
    }

    public function testUnauthenticatedUserCannotAccessUsersApi(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $client->request('GET', '/admin/api/users');

        self::assertContains($client->getResponse()->getStatusCode(), [302, 401]);
    }

    public function testUpdateRolesReturns404ForUnknownUser(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $superAdmin = $this->createAdminUser('super-404@example.test', ['ROLE_SUPER_ADMIN']);
        $client->loginUser($superAdmin);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/00000000-0000-0000-0000-000000000000/roles', [
            'roles' => ['ROLE_ADMIN'],
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('User not found', (string) $client->getResponse()->getContent());
    }

    public function testUpdateRolesReturns422WhenRolesPayloadIsInvalid(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $superAdmin = $this->createAdminUser('super-422@example.test', ['ROLE_SUPER_ADMIN']);
        $target = $this->createAdminUser('target-422@example.test', ['ROLE_EDITOR']);
        $client->loginUser($superAdmin);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.(string) $target->id().'/roles', [
            'roles' => [],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Validation failed', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('roles', (string) $client->getResponse()->getContent());
    }

    public function testUpdateRolesRejectsRequestWithoutCsrfHeader(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $superAdmin = $this->createAdminUser('super-csrf@example.test', ['ROLE_SUPER_ADMIN']);
        $target = $this->createAdminUser('target-csrf@example.test', ['ROLE_EDITOR']);
        $client->loginUser($superAdmin);

        $this->jsonRequest($client, 'PATCH', '/admin/api/users/'.(string) $target->id().'/roles', [
            'roles' => ['ROLE_ADMIN'],
        ], [
            'HTTP_ORIGIN' => 'http://zaborprofil.test',
            'HTTP_HOST' => 'zaborprofil.test',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('Invalid CSRF token', (string) $client->getResponse()->getContent());
    }

    public function testUpdateRolesRejectsRequestWithoutOriginHeader(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $superAdmin = $this->createAdminUser('super-origin-missing@example.test', ['ROLE_SUPER_ADMIN']);
        $target = $this->createAdminUser('target-origin-missing@example.test', ['ROLE_EDITOR']);
        $client->loginUser($superAdmin);

        $csrfHeader = $this->csrfHeader($client);
        $this->jsonRequest($client, 'PATCH', '/admin/api/users/'.(string) $target->id().'/roles', [
            'roles' => ['ROLE_ADMIN'],
        ], [
            ...$csrfHeader,
            'HTTP_HOST' => 'zaborprofil.test',
            'HTTP_REFERER' => '',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('Missing request origin', (string) $client->getResponse()->getContent());
    }

    public function testUpdateRolesRejectsRequestWithInvalidOriginHeader(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $superAdmin = $this->createAdminUser('super-origin-invalid@example.test', ['ROLE_SUPER_ADMIN']);
        $target = $this->createAdminUser('target-origin-invalid@example.test', ['ROLE_EDITOR']);
        $client->loginUser($superAdmin);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.(string) $target->id().'/roles', [
            'roles' => ['ROLE_ADMIN'],
        ], [
            'HTTP_ORIGIN' => 'https://attacker.example',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('Invalid request origin', (string) $client->getResponse()->getContent());
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
    private function jsonRequestWithCsrf(KernelBrowser $client, string $method, string $uri, array $payload = [], array $headers = []): void
    {
        $this->jsonRequest($client, $method, $uri, $payload, [
            ...$this->csrfHeader($client),
            'HTTP_ORIGIN' => 'http://zaborprofil.test',
            'HTTP_HOST' => 'zaborprofil.test',
            ...$headers,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    private function jsonRequest(KernelBrowser $client, string $method, string $uri, array $payload = [], array $headers = []): void
    {
        $client->jsonRequest($method, $uri, $payload, $headers);
    }

    /**
     * @return array<string, string>
     */
    private function csrfHeader(KernelBrowser $client): array
    {
        $client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        if (!preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', $html, $matches)) {
            throw new LogicException('Admin CSRF token meta tag was not rendered.');
        }

        return [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $matches[1],
        ];
    }
}

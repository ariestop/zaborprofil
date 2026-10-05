<?php

declare(strict_types=1);

namespace App\Tests\Functional\User;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\AuditLog\Domain\Entity\AuditLogEntry;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
        self::assertIsArray($updatedPayload['roles'] ?? null);
        self::assertContains('ROLE_ADMIN', $updatedPayload['roles']);
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

    public function testCreateUserHashesPasswordAndWritesAuditWithoutHash(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $admin = $this->createAdminUser('admin-create@example.test', ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/users', [
            'email' => 'New.Editor@Example.test',
            'password' => 'long-enough-password',
            'roles' => ['ROLE_EDITOR'],
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $this->decode($client);
        self::assertSame('new.editor@example.test', $payload['email'] ?? null);
        self::assertSame(['ROLE_EDITOR'], $payload['roles'] ?? null);
        self::assertStringNotContainsString('long-enough-password', (string) $client->getResponse()->getContent());

        $created = $this->entityManager()->getRepository(AdminUser::class)->findOneBy(['email' => 'new.editor@example.test']);
        self::assertInstanceOf(AdminUser::class, $created);
        self::assertNotSame('long-enough-password', $created->getPassword());

        $entries = $this->entityManager()->getRepository(AuditLogEntry::class)->findBy([
            'entityType' => AdminUser::class,
            'entityId' => (string) $created->id(),
        ]);
        self::assertCount(1, $entries);
        self::assertSame('admin-create@example.test', $entries[0]->actorEmail());
        self::assertStringNotContainsString($created->getPassword(), json_encode($entries[0]->newValues(), JSON_THROW_ON_ERROR));
    }

    public function testAdminCanSetClearAndValidateUserName(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $admin = $this->createAdminUser('admin-name@example.test', ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/users', [
            'name' => '  Игорь  ',
            'email' => 'igor@example.test',
            'password' => 'long-enough-password',
            'roles' => ['ROLE_EDITOR'],
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->decode($client);
        self::assertSame('Игорь', $created['name'] ?? null);
        $id = $created['id'] ?? null;
        self::assertIsString($id);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.$id.'/name', ['name' => 'Игорь Петров']);
        self::assertResponseIsSuccessful();
        self::assertSame('Игорь Петров', $this->decode($client)['name'] ?? null);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.$id.'/name', ['name' => str_repeat('я', 121)]);
        self::assertResponseStatusCodeSame(422);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.$id.'/name', ['name' => '   ']);
        self::assertResponseIsSuccessful();
        $cleared = $this->decode($client);
        self::assertArrayHasKey('name', $cleared);
        self::assertNull($cleared['name']);

        $client->request('GET', '/admin/api/users');
        $users = $this->decode($client)['users'] ?? null;
        self::assertIsArray($users);
        self::assertIsArray($users[0]);
        self::assertArrayHasKey('name', $users[0]);
    }

    public function testCreateUserRejectsDuplicateWeakPasswordAndUnknownRole(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('admin-dup@example.test', ['ROLE_ADMIN']));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/users', [
            'email' => 'ADMIN-dup@example.test',
            'password' => 'long-enough-password',
            'roles' => ['ROLE_EDITOR'],
        ]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('USER_ALREADY_EXISTS', $this->decode($client)['code'] ?? null);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/users', [
            'email' => 'weak@example.test',
            'password' => 'short',
            'roles' => ['ROLE_EDITOR'],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('не менее 12', json_encode($this->decode($client), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/users', [
            'email' => 'bad-role@example.test',
            'password' => 'long-enough-password',
            'roles' => ['ROLE_ROOT'],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager()->getRepository(AdminUser::class)->findOneBy(['email' => 'bad-role@example.test']));
    }

    public function testAdminCannotCreateSuperAdmin(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('admin-no-super@example.test', ['ROLE_ADMIN']));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/users', [
            'email' => 'boss@example.test',
            'password' => 'long-enough-password',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateRolesRejectsUnknownRoles(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('super-whitelist@example.test', ['ROLE_SUPER_ADMIN']));
        $target = $this->createAdminUser('target-whitelist@example.test', ['ROLE_EDITOR']);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.(string) $target->id().'/roles', [
            'roles' => ['ROLE_EDITOR', 'ROLE_GOD'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('ROLE_GOD', (string) $client->getResponse()->getContent());
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->getRepository(AdminUser::class)->find($target->id());
        self::assertInstanceOf(AdminUser::class, $reloaded);
        self::assertSame(['ROLE_EDITOR'], $reloaded->storedRoles());
    }

    public function testAdminCannotRemoveOwnAdministrativeRoleOrDeactivateOrDeleteSelf(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $admin = $this->createAdminUser('self@example.test', ['ROLE_ADMIN']);
        $this->createAdminUser('other-admin@example.test', ['ROLE_ADMIN']);
        $client->loginUser($admin);
        $id = (string) $admin->id();

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.$id.'/roles', ['roles' => ['ROLE_EDITOR']]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('SELF_LOCKOUT', $this->decode($client)['code'] ?? null);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.$id.'/active', ['active' => false]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('SELF_LOCKOUT', $this->decode($client)['code'] ?? null);

        $this->jsonRequestWithCsrf($client, 'DELETE', '/admin/api/users/'.$id);
        self::assertResponseStatusCodeSame(409);

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->getRepository(AdminUser::class)->find($admin->id());
        self::assertInstanceOf(AdminUser::class, $reloaded);
        self::assertTrue($reloaded->isActive());
        self::assertSame(['ROLE_ADMIN'], $reloaded->storedRoles());
    }

    public function testAdminCannotGrantOrRevokeSuperAdminRole(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $admin = $this->createAdminUser('plain-admin@example.test', ['ROLE_ADMIN']);
        $super = $this->createAdminUser('only-super@example.test', ['ROLE_SUPER_ADMIN']);
        $editor = $this->createAdminUser('plain-editor@example.test', ['ROLE_EDITOR']);
        $client->loginUser($admin);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.(string) $super->id().'/roles', ['roles' => ['ROLE_ADMIN']]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('ROLE_NOT_ALLOWED', $this->decode($client)['code'] ?? null);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.(string) $editor->id().'/roles', ['roles' => ['ROLE_SUPER_ADMIN']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCanDeactivateActivateAndDeleteAnotherUser(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $admin = $this->createAdminUser('manager-admin@example.test', ['ROLE_ADMIN']);
        $target = $this->createAdminUser('victim@example.test', ['ROLE_EDITOR']);
        $client->loginUser($admin);
        $targetId = (string) $target->id();

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.$targetId.'/active', ['active' => false]);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->decode($client)['active'] ?? null);

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/users/'.$targetId.'/active', ['active' => true]);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->decode($client)['active'] ?? null);

        $this->jsonRequestWithCsrf($client, 'DELETE', '/admin/api/users/'.$targetId);
        self::assertResponseStatusCodeSame(204);
        $this->entityManager()->clear();
        self::assertNull($this->entityManager()->getRepository(AdminUser::class)->find($target->id()));

        $actions = array_map(
            static fn (AuditLogEntry $entry): string => $entry->action(),
            $this->entityManager()->getRepository(AuditLogEntry::class)->findBy(['entityType' => AdminUser::class, 'entityId' => $targetId], ['occurredAt' => 'ASC']),
        );
        self::assertContains('delete', $actions);
        self::assertContains('update', $actions);
    }

    public function testAdministratorCanResetPasswordOfAnotherUser(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('reset-admin@example.test', ['ROLE_ADMIN']));
        $target = $this->createAdminUser('reset-target@example.test', ['ROLE_EDITOR']);
        $oldHash = $target->getPassword();

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/users/'.(string) $target->id().'/password', ['password' => 'short']);
        self::assertResponseStatusCodeSame(422);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/users/'.(string) $target->id().'/password', ['password' => 'a-very-new-password']);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('a-very-new-password', (string) $client->getResponse()->getContent());

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->getRepository(AdminUser::class)->find($target->id());
        self::assertInstanceOf(AdminUser::class, $reloaded);
        self::assertNotSame($oldHash, $reloaded->getPassword());

        $audit = $this->entityManager()->getRepository(AuditLogEntry::class)->findBy(['entityType' => AdminUser::class, 'entityId' => (string) $target->id(), 'action' => 'update']);
        self::assertNotEmpty($audit);
        self::assertStringNotContainsString($reloaded->getPassword(), json_encode($audit[0]->newValues(), JSON_THROW_ON_ERROR));
    }

    public function testUserCanChangeOwnPasswordOnlyWithCurrentPassword(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $user = $this->createAdminUser('own-password@example.test', ['ROLE_ADMIN']);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $user->changePasswordHash($hasher->hashPassword($user, 'current-password-1'));
        $this->entityManager()->flush();
        $client->loginUser($user);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/users/me/password', [
            'currentPassword' => 'wrong-current-password',
            'password' => 'brand-new-password-1',
        ]);
        self::assertResponseStatusCodeSame(422);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/users/me/password', [
            'currentPassword' => 'current-password-1',
            'password' => 'brand-new-password-1',
        ]);
        self::assertResponseIsSuccessful();

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->getRepository(AdminUser::class)->find($user->id());
        self::assertInstanceOf(AdminUser::class, $reloaded);
        self::assertTrue($hasher->isPasswordValid($reloaded, 'brand-new-password-1'));
    }

    public function testPasswordChangeInvalidatesExistingSessions(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $user = $this->createAdminUser('session@example.test', ['ROLE_ADMIN']);
        $client->loginUser($user);

        $client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful();

        $fresh = $this->entityManager()->getRepository(AdminUser::class)->find($user->id());
        self::assertInstanceOf(AdminUser::class, $fresh);
        $fresh->changePasswordHash('rotated-hash');
        $this->entityManager()->flush();

        $client->request('GET', '/admin/dashboard');
        self::assertResponseRedirects();
    }

    public function testNonAdministrativeStoredRolesAreVisibleInListWithoutImplicitAdminRole(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('list-admin@example.test', ['ROLE_ADMIN']));
        $this->createAdminUser('list-editor@example.test', ['ROLE_EDITOR']);

        $client->request('GET', '/admin/api/users');
        self::assertResponseIsSuccessful();
        $payload = $this->decode($client);
        self::assertIsArray($payload['availableRoles'] ?? null);
        self::assertContains('ROLE_MANAGER', $payload['availableRoles']);
        self::assertIsArray($payload['users'] ?? null);

        $roles = [];
        foreach ($payload['users'] as $user) {
            self::assertIsArray($user);
            self::assertIsString($user['email'] ?? null);
            $roles[$user['email']] = $user['roles'] ?? null;
        }
        self::assertSame(['ROLE_EDITOR'], $roles['list-editor@example.test'] ?? null);
    }

    /**
     * @return array<mixed>
     */
    private function decode(KernelBrowser $client): array
    {
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }

    /**
     * @param list<string> $roles
     */
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
     * @param array<string, string> $headers
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

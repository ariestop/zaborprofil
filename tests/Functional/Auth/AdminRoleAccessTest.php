<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Вход в админку для ролей Editor/SEO/Manager и список прав, который SPA использует для меню.
 */
final class AdminRoleAccessTest extends WebTestCase
{
    /**
     * @param list<string> $expectedPermissions
     */
    #[DataProvider('rolePermissionsProvider')]
    public function testRoleOpensAdminShellAndGetsOwnPermissions(string $role, array $expectedPermissions): void
    {
        $client = $this->clientFor([$role]);

        $client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#admin-app');

        $client->request('GET', '/admin/api/me');
        self::assertResponseIsSuccessful();
        $payload = $this->decode($client);

        self::assertSame('role-user@example.test', $payload['email'] ?? null);
        self::assertSame([$role], $payload['roles'] ?? null);

        $permissions = $payload['permissions'] ?? null;
        self::assertIsArray($permissions);
        sort($permissions);
        sort($expectedPermissions);
        self::assertSame($expectedPermissions, $permissions);
    }

    public function testShellExposesPermissionsAsDataAttribute(): void
    {
        $client = $this->clientFor(['ROLE_MANAGER']);

        $crawler = $client->request('GET', '/admin/crm');

        self::assertResponseIsSuccessful();
        $permissions = explode(',', (string) $crawler->filter('#admin-app')->attr('data-permissions'));
        self::assertContains(AdminPermission::LEADS_VIEW, $permissions);
        self::assertNotContains(AdminPermission::SYSTEM_VIEW, $permissions);
        self::assertSame('ROLE_MANAGER', $crawler->filter('#admin-app')->attr('data-roles'));
    }

    #[DataProvider('forbiddenApiProvider')]
    public function testRoleIsDeniedOutsideItsPermissions(string $role, string $uri): void
    {
        $client = $this->clientFor([$role]);

        $client->request('GET', $uri);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('ACCESS_DENIED', $this->decode($client)['code'] ?? null);
    }

    #[DataProvider('allowedApiProvider')]
    public function testRoleReachesItsOwnApi(string $role, string $uri): void
    {
        $client = $this->clientFor([$role]);

        $client->request('GET', $uri);

        self::assertResponseIsSuccessful();
    }

    public function testAdministratorGetsEveryPermissionExceptDangerousOnes(): void
    {
        $client = $this->clientFor(['ROLE_ADMIN']);

        $client->request('GET', '/admin/api/me');

        $permissions = $this->decode($client)['permissions'] ?? null;
        self::assertIsArray($permissions);
        self::assertContains(AdminPermission::USERS_MANAGE, $permissions);
        self::assertContains(AdminPermission::SYSTEM_VIEW, $permissions);
        self::assertNotContains(AdminPermission::SYSTEM_DANGEROUS, $permissions);
    }

    public function testSuperAdminGetsDangerousPermission(): void
    {
        $client = $this->clientFor(['ROLE_SUPER_ADMIN']);

        $client->request('GET', '/admin/api/me');

        $permissions = $this->decode($client)['permissions'] ?? null;
        self::assertIsArray($permissions);
        self::assertEqualsCanonicalizing(AdminPermission::all(), $permissions);
    }

    public function testAccountWithoutAdminRoleCannotOpenAdmin(): void
    {
        $client = $this->clientFor(['ROLE_USER']);

        $client->request('GET', '/admin/dashboard');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAccountWithoutAdminRoleCannotLogIn(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $this->persistUser(['ROLE_USER'], $this->hasher()->hashPassword(new AdminUser('x@example.test', 'tmp', []), 'correct-password'));

        $crawler = $client->request('GET', '/admin/login');
        $client->submit($crawler->selectButton('Войти')->form([
            '_username' => 'role-user@example.test',
            '_password' => 'correct-password',
        ]));
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'нет доступа к админ-панели');
    }

    #[DataProvider('adminRolesProvider')]
    public function testEveryAdminRoleCanLogInThroughForm(string $role): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $this->persistUser([$role], $this->hasher()->hashPassword(new AdminUser('x@example.test', 'tmp', []), 'correct-password'));

        $crawler = $client->request('GET', '/admin/login');
        $client->submit($crawler->selectButton('Войти')->form([
            '_username' => 'role-user@example.test',
            '_password' => 'correct-password',
        ]));

        self::assertResponseRedirects('/admin/dashboard');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#admin-app');
    }

    public function testAnonymousUserDoesNotGetProfile(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/api/me');

        self::assertResponseRedirects('/admin/login');
    }

    /**
     * @return iterable<string, array{0:string, 1:list<string>}>
     */
    public static function rolePermissionsProvider(): iterable
    {
        yield 'editor' => ['ROLE_EDITOR', [
            AdminPermission::PAGES_VIEW,
            AdminPermission::PAGES_CREATE,
            AdminPermission::PAGES_EDIT,
            AdminPermission::PAGES_SUBMIT_REVIEW,
            AdminPermission::PAGES_VIEW_REVISIONS,
            AdminPermission::BLOCKS_CREATE,
            AdminPermission::BLOCKS_EDIT,
            AdminPermission::BLOCKS_REORDER,
            AdminPermission::BLOCKS_CLONE,
            AdminPermission::MEDIA_UPLOAD,
            AdminPermission::CATALOG_VIEW,
        ]];
        yield 'seo' => ['ROLE_SEO', [
            AdminPermission::PAGES_VIEW,
            AdminPermission::PAGES_SUBMIT_REVIEW,
            AdminPermission::PAGES_APPROVE,
            AdminPermission::PAGES_VIEW_REVISIONS,
            AdminPermission::SEO_EDIT,
            AdminPermission::SEO_APPROVE,
        ]];
        yield 'manager' => ['ROLE_MANAGER', [
            AdminPermission::LEADS_VIEW,
            AdminPermission::LEADS_MANAGE,
            AdminPermission::CATALOG_VIEW,
        ]];
    }

    /**
     * @return iterable<string, array{0:string, 1:string}>
     */
    public static function forbiddenApiProvider(): iterable
    {
        yield 'editor: leads' => ['ROLE_EDITOR', '/admin/api/leads'];
        yield 'editor: seo redirects' => ['ROLE_EDITOR', '/admin/api/seo/redirects'];
        yield 'editor: users' => ['ROLE_EDITOR', '/admin/api/users'];
        yield 'editor: settings' => ['ROLE_EDITOR', '/admin/api/settings'];
        yield 'editor: system overview' => ['ROLE_EDITOR', '/admin/api/system/overview'];
        yield 'seo: leads' => ['ROLE_SEO', '/admin/api/leads'];
        yield 'seo: media' => ['ROLE_SEO', '/admin/api/media/assets'];
        yield 'seo: users' => ['ROLE_SEO', '/admin/api/users'];
        yield 'seo: system health' => ['ROLE_SEO', '/admin/api/system/health'];
        yield 'manager: pages' => ['ROLE_MANAGER', '/admin/api/content/pages'];
        yield 'manager: media' => ['ROLE_MANAGER', '/admin/api/media/assets'];
        yield 'manager: seo redirects' => ['ROLE_MANAGER', '/admin/api/seo/redirects'];
        yield 'manager: settings' => ['ROLE_MANAGER', '/admin/api/settings'];
        yield 'manager: system overview' => ['ROLE_MANAGER', '/admin/api/system/overview'];
        yield 'manager: system backups' => ['ROLE_MANAGER', '/admin/api/system/backups'];
        yield 'manager: audit' => ['ROLE_MANAGER', '/admin/api/system/audit'];
    }

    /**
     * @return iterable<string, array{0:string, 1:string}>
     */
    public static function allowedApiProvider(): iterable
    {
        yield 'editor: pages' => ['ROLE_EDITOR', '/admin/api/content/pages'];
        yield 'editor: media' => ['ROLE_EDITOR', '/admin/api/media/assets'];
        yield 'seo: pages' => ['ROLE_SEO', '/admin/api/content/pages'];
        yield 'seo: redirects' => ['ROLE_SEO', '/admin/api/seo/redirects'];
        yield 'manager: leads' => ['ROLE_MANAGER', '/admin/api/leads'];
        yield 'manager: leads summary' => ['ROLE_MANAGER', '/admin/api/leads/summary'];
    }

    /**
     * @return iterable<string, array{0:string}>
     */
    public static function adminRolesProvider(): iterable
    {
        yield 'super admin' => ['ROLE_SUPER_ADMIN'];
        yield 'admin' => ['ROLE_ADMIN'];
        yield 'editor' => ['ROLE_EDITOR'];
        yield 'seo' => ['ROLE_SEO'];
        yield 'manager' => ['ROLE_MANAGER'];
    }

    /**
     * @param list<string> $roles
     */
    private function clientFor(array $roles): KernelBrowser
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->persistUser($roles));

        return $client;
    }

    /**
     * @param list<string> $roles
     */
    private function persistUser(array $roles, string $passwordHash = 'hash'): AdminUser
    {
        $user = new AdminUser('role-user@example.test', $passwordHash, $roles);
        $entityManager = $this->entityManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(KernelBrowser $client): array
    {
        $decoded = json_decode($client->getResponse()->getContent() ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            throw new LogicException('JSON object expected.');
        }

        $payload = [];
        foreach ($decoded as $key => $value) {
            $payload[(string) $key] = $value;
        }

        return $payload;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }

    private function hasher(): UserPasswordHasherInterface
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        if (!$hasher instanceof UserPasswordHasherInterface) {
            throw new LogicException('Password hasher is not available.');
        }

        return $hasher;
    }
}

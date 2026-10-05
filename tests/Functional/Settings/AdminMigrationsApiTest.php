<?php

declare(strict_types=1);

namespace App\Tests\Functional\Settings;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use App\Tests\Support\Settings\FakeMigrationAdminService;
use Doctrine\Migrations\Exception\MigrationClassNotFound;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminMigrationsApiTest extends WebTestCase
{
    private const string VERSION = 'DoctrineMigrations\\Version2';

    private const string LATEST_VERSION = 'DoctrineMigrations\\Version1';

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        FakeMigrationAdminService::reset();
    }

    protected function tearDown(): void
    {
        $this->setWebMigrationsFlag('1');
        FakeMigrationAdminService::reset();
        parent::tearDown();
    }

    public function testRoleAdminCannotRunMigrationsFromWeb(): void
    {
        $client = $this->clientFor('admin-migrations@example.test', ['ROLE_ADMIN']);

        $client->request('GET', '/admin/api/settings/migrations');
        self::assertResponseIsSuccessful();
        foreach ($this->items($client) as $item) {
            self::assertFalse($item['canApply']);
            self::assertFalse($item['canRollback']);
            self::assertFalse($item['actionsAllowed']);
        }

        $this->postWithCsrf($client, $this->applyUri(), ['confirmToken' => 'fake']);
        self::assertResponseStatusCodeSame(403);

        $this->postWithCsrf($client, $this->rollbackUri(), ['confirmToken' => 'fake']);
        self::assertResponseStatusCodeSame(403);

        $this->postWithCsrf($client, '/admin/api/system/security/confirm-token', ['action' => 'migration.apply:'.self::VERSION]);
        self::assertResponseStatusCodeSame(403);

        self::assertSame([], FakeMigrationAdminService::$calls);
    }

    public function testSuperAdminSeesAvailableActionsInList(): void
    {
        $client = $this->clientFor('root-migrations-list@example.test', ['ROLE_SUPER_ADMIN']);

        $client->request('GET', '/admin/api/settings/migrations');
        self::assertResponseIsSuccessful();
        $items = $this->items($client);
        self::assertCount(2, $items);
        self::assertTrue($items[0]['canRollback']);
        self::assertTrue($items[1]['canApply']);
        self::assertTrue($items[1]['actionsAllowed']);
    }

    public function testApplyRequiresConfirmTokenForTheSameActionAndVersion(): void
    {
        $client = $this->clientFor('root-migrations-token@example.test', ['ROLE_SUPER_ADMIN']);

        $this->postWithCsrf($client, $this->applyUri(), []);
        self::assertResponseStatusCodeSame(422);

        $this->postWithCsrf($client, $this->applyUri(), ['confirmToken' => 'missing']);
        self::assertResponseStatusCodeSame(422);

        $rollbackToken = $this->issueToken($client, 'migration.rollback:'.self::VERSION);
        $this->postWithCsrf($client, $this->applyUri(), ['confirmToken' => $rollbackToken]);
        self::assertResponseStatusCodeSame(422);

        self::assertSame([], FakeMigrationAdminService::$calls);

        $audit = $this->auditActions($client);
        self::assertContains('system.migration.attempt', $audit);
        self::assertContains('system.migration.failure', $audit);
        self::assertNotContains('system.migration.success', $audit);
    }

    public function testApplyAndRollbackWithValidTokenAreAuditedAndTokenIsSingleUse(): void
    {
        $client = $this->clientFor('root-migrations-ok@example.test', ['ROLE_SUPER_ADMIN']);

        $token = $this->issueToken($client, 'migration.apply:'.self::VERSION);
        $this->postWithCsrf($client, $this->applyUri(), ['confirmToken' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'applied', 'executedCount' => 1], $this->decode($client));

        $this->postWithCsrf($client, $this->applyUri(), ['confirmToken' => $token]);
        self::assertResponseStatusCodeSame(422);

        $rollbackToken = $this->issueToken($client, 'migration.rollback:'.self::LATEST_VERSION);
        $this->postWithCsrf($client, $this->rollbackUri(), ['confirmToken' => $rollbackToken]);
        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'rolled_back', 'executedCount' => 1], $this->decode($client));

        self::assertSame(['apply:'.self::VERSION, 'rollback:'.self::LATEST_VERSION], FakeMigrationAdminService::$calls);

        $audit = $this->auditActions($client);
        self::assertSame(2, \count(array_filter($audit, static fn (string $action): bool => $action === 'system.migration.success')));
    }

    public function testUnexpectedFailureDoesNotLeakExceptionMessageAndIsAudited(): void
    {
        $client = $this->clientFor('root-migrations-fail@example.test', ['ROLE_SUPER_ADMIN']);
        FakeMigrationAdminService::$failure = new RuntimeException('SQLSTATE[42S01]: Base table or view already exists: /var/www/secret_table');

        $token = $this->issueToken($client, 'migration.apply:'.self::VERSION);
        $this->postWithCsrf($client, $this->applyUri(), ['confirmToken' => $token]);

        self::assertResponseStatusCodeSame(500);
        self::assertSame(['error' => 'Internal server error', 'code' => 'INTERNAL'], $this->decode($client));

        $client->request('GET', '/admin/api/system/audit?limit=200');
        $auditBody = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('system.migration.failure', $auditBody);
        self::assertStringNotContainsString('SQLSTATE', $auditBody);
        self::assertStringNotContainsString('secret_table', $auditBody);
    }

    public function testUnknownMigrationVersionReturnsNotFound(): void
    {
        $client = $this->clientFor('root-migrations-404@example.test', ['ROLE_SUPER_ADMIN']);
        FakeMigrationAdminService::$failure = MigrationClassNotFound::new('DoctrineMigrations\\Unknown');

        $token = $this->issueToken($client, 'migration.apply:DoctrineMigrations\\Unknown');
        $this->postWithCsrf($client, '/admin/api/settings/migrations/'.rawurlencode('DoctrineMigrations\\Unknown').'/apply', ['confirmToken' => $token]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Migration not found.', 'code' => 'NOT_FOUND'], $this->decode($client));
    }

    public function testDisabledFlagBlocksMigrationsEvenForSuperAdmin(): void
    {
        $this->setWebMigrationsFlag('0');
        $client = $this->clientFor('root-migrations-off@example.test', ['ROLE_SUPER_ADMIN']);

        $client->request('GET', '/admin/api/settings/migrations');
        self::assertResponseIsSuccessful();
        foreach ($this->items($client) as $item) {
            self::assertFalse($item['actionsAllowed']);
            self::assertFalse($item['canApply']);
            self::assertFalse($item['canRollback']);
        }

        $this->postWithCsrf($client, $this->applyUri(), ['confirmToken' => 'any']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('MIGRATIONS_DISABLED', $this->decodeArray($client)['code'] ?? null);
        self::assertSame([], FakeMigrationAdminService::$calls);
    }

    private function applyUri(): string
    {
        return '/admin/api/settings/migrations/'.rawurlencode(self::VERSION).'/apply';
    }

    private function rollbackUri(): string
    {
        return '/admin/api/settings/migrations/'.rawurlencode(self::LATEST_VERSION).'/rollback';
    }

    private function setWebMigrationsFlag(string $value): void
    {
        $_ENV['ADMIN_WEB_MIGRATIONS_ENABLED'] = $value;
        $_SERVER['ADMIN_WEB_MIGRATIONS_ENABLED'] = $value;
        putenv('ADMIN_WEB_MIGRATIONS_ENABLED='.$value);
    }

    /**
     * @param list<string> $roles
     */
    private function clientFor(string $email, array $roles): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $entityManager = $this->entityManager();
        $user = new AdminUser($email, 'hash', $roles);
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        return $client;
    }

    private function issueToken(KernelBrowser $client, string $action): string
    {
        $this->postWithCsrf($client, '/admin/api/system/security/confirm-token', ['action' => $action]);
        self::assertResponseStatusCodeSame(201);
        $token = $this->decodeArray($client)['confirmToken'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    /**
     * @return list<string>
     */
    private function auditActions(KernelBrowser $client): array
    {
        $client->request('GET', '/admin/api/system/audit?limit=200');
        self::assertResponseIsSuccessful();

        $actions = [];
        foreach ($this->decode($client) as $entry) {
            if (\is_array($entry) && \is_string($entry['action'] ?? null)) {
                $actions[] = $entry['action'];
            }
        }

        return $actions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(KernelBrowser $client): array
    {
        $items = [];
        foreach ($this->decode($client) as $item) {
            if (\is_array($item)) {
                $normalized = [];
                foreach ($item as $key => $value) {
                    if (\is_string($key)) {
                        $normalized[$key] = $value;
                    }
                }
                $items[] = $normalized;
            }
        }

        return $items;
    }

    /**
     * @return array<mixed>
     */
    private function decode(KernelBrowser $client): array
    {
        $decoded = json_decode($client->getResponse()->getContent() ?: '[]', true, flags: JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeArray(KernelBrowser $client): array
    {
        $result = [];
        foreach ($this->decode($client) as $key => $value) {
            if (\is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
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
    private function postWithCsrf(KernelBrowser $client, string $uri, array $payload): void
    {
        $client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        if (!preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', $html, $matches)) {
            throw new LogicException('Admin CSRF token meta tag was not rendered.');
        }

        $client->jsonRequest('POST', $uri, $payload, [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $matches[1],
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SystemCenterDangerousActionsTest extends WebTestCase
{
    public function testRoleAdminCanReadButCannotRunDangerousSystemActions(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createUserWithRoles('admin@example.test', ['ROLE_ADMIN']));

        $client->request('GET', '/admin/api/system/processes');
        self::assertResponseIsSuccessful();

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/system/security/confirm-token', [
            'action' => 'cache.clear.app',
        ]);
        self::assertResponseStatusCodeSame(403);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/system/cache/clear', [
            'confirmToken' => 'fake',
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testRoleSuperAdminNeedsValidConfirmTokenAndCreatesAuditLog(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createUserWithRoles('root@example.test', ['ROLE_SUPER_ADMIN']));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/system/cache/clear', [
            'confirmToken' => 'missing',
        ]);
        self::assertResponseStatusCodeSame(422);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/system/security/confirm-token', [
            'action' => 'cache.clear.app',
        ]);
        self::assertResponseStatusCodeSame(201);
        $tokenPayload = json_decode($client->getResponse()->getContent() ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($tokenPayload);
        self::assertArrayHasKey('confirmToken', $tokenPayload);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/system/cache/clear', [
            'confirmToken' => (string) $tokenPayload['confirmToken'],
        ]);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/api/system/audit?limit=200');
        self::assertResponseIsSuccessful();
        $audit = json_decode($client->getResponse()->getContent() ?: '[]', true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($audit);
        self::assertTrue($this->containsAuditAction($audit, 'system.cache.success'));
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function containsAuditAction(array $entries, string $expectedAction): bool
    {
        foreach ($entries as $entry) {
            if (($entry['action'] ?? null) === $expectedAction) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $roles
     */
    private function createUserWithRoles(string $email, array $roles): AdminUser
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
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ]);
    }
}

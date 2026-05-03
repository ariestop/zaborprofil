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

final class AuditLogApiTest extends WebTestCase
{
    public function testSettingChangeCreatesAuditEntryVisibleInAdminApi(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser());

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/settings/site/name', [
            'value' => 'ЗаборПрофиль',
        ]);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/api/system/audit');
        self::assertResponseIsSuccessful();

        $payload = json_decode($client->getResponse()->getContent() ?: '[]', true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertNotEmpty($payload);
        self::assertIsArray($payload[0]);
        self::assertSame('create', $payload[0]['action'] ?? null);
        self::assertSame('audit@example.test', $payload[0]['actorEmail'] ?? null);
    }

    private function createAdminUser(): AdminUser
    {
        $entityManager = $this->entityManager();
        $user = new AdminUser('audit@example.test', 'hash', ['ROLE_ADMIN']);
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

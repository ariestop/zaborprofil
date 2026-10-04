<?php

declare(strict_types=1);

namespace App\Tests\Support\Admin;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * База для функциональных тестов Admin API: чистая MySQL-схема, вход ROLE_ADMIN, CSRF и Origin для мутаций.
 */
abstract class AdminApiTestCase extends WebTestCase
{
    private ?string $csrfToken = null;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->csrfToken = null;
    }

    protected function adminClient(string $email = 'admin-api@example.test'): KernelBrowser
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $entityManager = $this->entityManager();
        $user = new AdminUser($email, 'hash', ['ROLE_ADMIN']);
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        return $client;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function api(KernelBrowser $client, string $method, string $uri, array $payload = []): void
    {
        if ($method === 'GET') {
            $client->request('GET', $uri);

            return;
        }

        $client->jsonRequest($method, $uri, $payload, [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $this->csrfToken($client),
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(KernelBrowser $client): array
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

    /**
     * @return list<array<string, mixed>>
     */
    protected function rows(mixed $value): array
    {
        self::assertIsArray($value);

        $rows = [];
        foreach ($value as $row) {
            self::assertIsArray($row);
            $normalized = [];
            foreach ($row as $key => $item) {
                $normalized[(string) $key] = $item;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    protected function text(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }

    protected function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }

    private function csrfToken(KernelBrowser $client): string
    {
        if ($this->csrfToken !== null) {
            return $this->csrfToken;
        }

        $client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful();

        if (!preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', (string) $client->getResponse()->getContent(), $matches)) {
            throw new LogicException('Admin CSRF token meta tag was not rendered.');
        }

        return $this->csrfToken = $matches[1];
    }
}

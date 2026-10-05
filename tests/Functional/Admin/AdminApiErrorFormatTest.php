<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\Menu\Domain\Entity\MenuItem;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminApiErrorFormatTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testUncaughtDatabaseFailureReturnsGenericJson500(): void
    {
        $client = $this->adminClient();
        $tableName = $this->entityManager()->getClassMetadata(MenuItem::class)->getTableName();
        $this->entityManager()->getConnection()->executeStatement(\sprintf('DROP TABLE `%s`', $tableName));

        $client->request('GET', '/admin/api/menu/items');

        self::assertResponseStatusCodeSame(500);
        self::assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        self::assertSame(['error' => 'Internal server error', 'code' => 'INTERNAL'], $this->payload($client));
        self::assertStringNotContainsString($tableName, (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('SQLSTATE', (string) $client->getResponse()->getContent());
    }

    public function testUnsupportedMethodReturnsGenericJson405(): void
    {
        $client = $this->adminClient();

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/menu/items', []);

        self::assertResponseStatusCodeSame(405);
        self::assertSame('METHOD_NOT_ALLOWED', $this->payload($client)['code'] ?? null);
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function validationEndpoints(): iterable
    {
        yield 'menu create' => ['POST', '/admin/api/menu/items', []];
        yield 'redirect create' => ['POST', '/admin/api/seo/redirects', []];
        yield 'settings upsert' => ['PUT', '/admin/api/settings/site/name', []];
        yield 'robots update' => ['PUT', '/admin/api/seo/robots', ['body' => 123]];
        yield 'lead status with invalid id' => ['PATCH', '/admin/api/leads/not-a-ulid/status', ['status' => 'new']];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('validationEndpoints')]
    public function testValidationErrorsUseUnifiedFormat(string $method, string $uri, array $body): void
    {
        $client = $this->adminClient();

        $this->jsonRequestWithCsrf($client, $method, $uri, $body);

        self::assertResponseStatusCodeSame(422);
        $payload = $this->payload($client);
        self::assertSame('VALIDATION', $payload['code'] ?? null);
        self::assertIsString($payload['error'] ?? null);
        self::assertNotSame('', $payload['error']);
    }

    public function testMissingEntityReturnsNotFoundWithCode(): void
    {
        $client = $this->adminClient();

        $this->jsonRequestWithCsrf($client, 'PATCH', '/admin/api/leads/01HZZZZZZZZZZZZZZZZZZZZZZZ/status', ['status' => 'new']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('NOT_FOUND', $this->payload($client)['code'] ?? null);
    }

    private function adminClient(): KernelBrowser
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $entityManager = $this->entityManager();
        $user = new AdminUser('error-format@example.test', 'hash', ['ROLE_ADMIN']);
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(KernelBrowser $client): array
    {
        $decoded = json_decode($client->getResponse()->getContent() ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        $payload = [];
        if (\is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                if (\is_string($key)) {
                    $payload[$key] = $value;
                }
            }
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

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequestWithCsrf(KernelBrowser $client, string $method, string $uri, array $payload): void
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

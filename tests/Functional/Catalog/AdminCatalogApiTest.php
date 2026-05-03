<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\Catalog\Domain\Enum\ProductStatus;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminCatalogApiTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testAdminCanCreateCatalogProductAndVariant(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('catalog@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/catalog/categories', [
            'title' => 'Заборы',
            'slug' => 'zabory',
            'path' => '/catalog/zabory/',
            'description' => 'Категория заборов',
            'sortOrder' => 10,
            'isActive' => true,
        ]);
        self::assertResponseStatusCodeSame(201);
        $categoryId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/catalog/products', [
            'categoryId' => $categoryId,
            'name' => 'Забор жалюзи',
            'slug' => 'zabor-jaluzi',
            'path' => '/catalog/zabory/zabor-jaluzi/',
            'status' => ProductStatus::Published->value,
            'summary' => 'Металлический забор жалюзи под ключ.',
            'description' => 'Описание товара.',
            'isIndexable' => true,
        ]);
        self::assertResponseStatusCodeSame(201);
        $productId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/catalog/products/'.$productId.'/variants', [
            'sku' => 'ZBR-JAL-200',
            'title' => 'Высота 2м',
            'priceCents' => 1250000,
            'currency' => 'RUB',
            'sortOrder' => 0,
            'isActive' => true,
        ]);
        self::assertResponseStatusCodeSame(201);
        $variantId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $client->request('GET', '/admin/api/catalog/products');
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertSame([ProductStatus::Draft->value, ProductStatus::Published->value, ProductStatus::Archived->value], $payload['statuses'] ?? null);
        $productPayload = self::firstArray($payload['products'] ?? null);
        $variantPayload = self::firstArray($productPayload['variants'] ?? null);
        self::assertSame($productId, $productPayload['id'] ?? null);
        self::assertSame($categoryId, $productPayload['categoryId'] ?? null);
        self::assertSame($variantId, $variantPayload['id'] ?? null);

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/catalog/variants/'.$variantId, [
            'sku' => 'ZBR-JAL-250',
            'title' => 'Высота 2.5м',
            'priceCents' => 1450000,
            'currency' => 'RUB',
            'sortOrder' => 1,
            'isActive' => false,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('ZBR-JAL-250', $this->stringFromResponse((string) $client->getResponse()->getContent(), 'sku'));

        $this->jsonRequestWithCsrf($client, 'DELETE', '/admin/api/catalog/products/'.$productId);
        self::assertResponseStatusCodeSame(204);
    }

    public function testInvalidVariantPayloadReturnsValidationError(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('catalog-validation@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/catalog/products', [
            'name' => 'Забор',
            'slug' => 'zabor',
            'path' => '/catalog/zabor/',
        ]);
        self::assertResponseStatusCodeSame(201);
        $productId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/catalog/products/'.$productId.'/variants', [
            'sku' => '!',
            'title' => 'Некорректный SKU',
            'priceCents' => 1000,
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Catalog variant SKU must contain', (string) $client->getResponse()->getContent());
    }

    /**
     * @param list<string> $roles
     */
    private function createAdminUser(string $email, array $roles = ['ROLE_ADMIN']): AdminUser
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

    private function stringFromResponse(string $json, string $key): string
    {
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!\is_array($payload) || !\is_string($payload[$key] ?? null)) {
            throw new LogicException(\sprintf('Response key "%s" is missing or is not a string.', $key));
        }

        return $payload[$key];
    }

    /**
     * @return array<string, mixed>
     */
    private static function firstArray(mixed $value): array
    {
        self::assertIsArray($value);
        self::assertIsArray($value[0] ?? null);

        $first = [];
        foreach ($value[0] as $key => $item) {
            self::assertIsString($key);
            $first[$key] = $item;
        }

        return $first;
    }
}

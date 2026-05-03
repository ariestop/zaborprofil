<?php

declare(strict_types=1);

namespace App\Tests\Functional\Menu;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\Menu\Application\Service\MenuProvider;
use App\Module\Menu\Domain\Entity\MenuItem;
use App\Module\Menu\Domain\Repository\MenuItemRepositoryInterface;
use App\Module\Menu\Domain\ValueObject\MenuPosition;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminMenuApiTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testAdminCanCreateListUpdateAndDeleteMenuItem(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('menu@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/menu/items', [
            'position' => MenuPosition::HEADER,
            'label' => 'Каталог',
            'url' => '/catalog/',
            'sortOrder' => 10,
            'isActive' => true,
        ]);
        self::assertResponseStatusCodeSame(201);
        $id = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $client->request('GET', '/admin/api/menu/items');
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $items = $payload['items'] ?? null;
        $positions = $payload['positions'] ?? null;
        self::assertIsArray($items);
        self::assertIsArray($positions);
        self::assertIsArray($items[0] ?? null);
        self::assertIsArray($positions[0] ?? null);
        self::assertSame(MenuPosition::HEADER, $items[0]['position'] ?? null);
        self::assertSame(MenuPosition::HEADER, $positions[0]['value'] ?? null);

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/menu/items/'.$id, [
            'position' => MenuPosition::FOOTER,
            'label' => 'О компании',
            'url' => '/company/',
            'sortOrder' => 20,
            'isActive' => false,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(MenuPosition::FOOTER, $this->stringFromResponse((string) $client->getResponse()->getContent(), 'position'));

        $this->jsonRequestWithCsrf($client, 'DELETE', '/admin/api/menu/items/'.$id);
        self::assertResponseStatusCodeSame(204);
    }

    public function testUpdatingMenuItemInvalidatesCachedPosition(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('menu-cache@example.test'));

        $item = new MenuItem(MenuPosition::HEADER, 'Старый пункт', '/old/', 0, true);
        $this->items()->save($item);

        $provider = self::getContainer()->get(MenuProvider::class);
        if (!$provider instanceof MenuProvider) {
            throw new LogicException('MenuProvider service is not available.');
        }

        $cachedItems = $provider->items(MenuPosition::HEADER);
        self::assertNotEmpty($cachedItems);
        self::assertSame('Старый пункт', $cachedItems[0]['label']);

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/menu/items/'.(string) $item->id(), [
            'position' => MenuPosition::HEADER,
            'label' => 'Новый пункт',
            'url' => '/new/',
            'sortOrder' => 0,
            'isActive' => true,
        ]);
        self::assertResponseIsSuccessful();

        $refreshedItems = $provider->items(MenuPosition::HEADER);
        self::assertNotEmpty($refreshedItems);
        self::assertSame('Новый пункт', $refreshedItems[0]['label']);
    }

    private function createAdminUser(string $email): AdminUser
    {
        $entityManager = $this->entityManager();
        $user = new AdminUser($email, 'hash', ['ROLE_ADMIN']);
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

    private function items(): MenuItemRepositoryInterface
    {
        $items = self::getContainer()->get(MenuItemRepositoryInterface::class);

        if (!$items instanceof MenuItemRepositoryInterface) {
            throw new LogicException('Menu item repository service is not available.');
        }

        return $items;
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
}

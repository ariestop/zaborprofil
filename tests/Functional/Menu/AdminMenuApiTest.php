<?php

declare(strict_types=1);

namespace App\Tests\Functional\Menu;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\Menu\Application\Service\MenuProvider;
use App\Module\Menu\Domain\Entity\MenuItem;
use App\Module\Menu\Domain\Repository\MenuItemRepositoryInterface;
use App\Module\Menu\Domain\ValueObject\MenuPosition;
use App\Module\User\Domain\Entity\AdminUser;
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

    public function testSectionWithChildrenIsServedAsTreeAndInactiveSectionHidesChildren(): void
    {
        $client = $this->adminClient('menu-tree@example.test');

        $section = $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Заборы', 'url' => '/#catalog']);
        $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Забор Жалюзи', 'url' => '/zabor-jaluzi/', 'parentId' => $section, 'hint' => 'Металлические ламели']);
        $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Евроштакетник', 'url' => '/zabor-iz-euroshtaketnika/', 'parentId' => $section]);
        $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Контакты', 'url' => '/contacts/']);

        $tree = $this->provider()->items(MenuPosition::HEADER);
        self::assertSame(['Заборы', 'Контакты'], array_column($tree, 'label'), 'Новые пункты встают в конец, подпункты — внутрь раздела.');
        self::assertIsArray($tree[0]['children']);
        self::assertSame(['Забор Жалюзи', 'Евроштакетник'], array_column($tree[0]['children'], 'label'));
        self::assertIsArray($tree[0]['children'][0]);
        self::assertSame('Металлические ламели', $tree[0]['children'][0]['hint']);

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/menu/items/'.$section, ['position' => MenuPosition::HEADER, 'label' => 'Заборы', 'url' => '/#catalog', 'isActive' => false]);
        self::assertResponseIsSuccessful();
        self::assertSame(['Контакты'], array_column($this->provider()->items(MenuPosition::HEADER), 'label'), 'Выключенный раздел скрывает и подпункты.');
    }

    public function testTreeRulesRejectThirdLevelForeignParentAndSelfParent(): void
    {
        $client = $this->adminClient('menu-rules@example.test');
        $section = $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Заборы', 'url' => '/#catalog']);
        $child = $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Жалюзи', 'url' => '/zabor-jaluzi/', 'parentId' => $section]);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/menu/items', ['position' => MenuPosition::HEADER, 'label' => 'Третий уровень', 'url' => '/x/', 'parentId' => $child]);
        self::assertResponseStatusCodeSame(422);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/menu/items', ['position' => MenuPosition::FOOTER, 'label' => 'Чужой раздел', 'url' => '/x/', 'parentId' => $section]);
        self::assertResponseStatusCodeSame(422);

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/menu/items/'.$section, ['position' => MenuPosition::HEADER, 'label' => 'Заборы', 'url' => '/#catalog', 'parentId' => $section]);
        self::assertResponseStatusCodeSame(422);

        $other = $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Материалы', 'url' => '/#materials']);
        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/menu/items/'.$section, ['position' => MenuPosition::HEADER, 'label' => 'Заборы', 'url' => '/#catalog', 'parentId' => $other]);
        self::assertResponseStatusCodeSame(422, 'Раздел с подпунктами нельзя вложить в другой раздел.');
    }

    public function testReorderMovesItemsBetweenLevelsInOneRequest(): void
    {
        $client = $this->adminClient('menu-order@example.test');
        $fences = $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Заборы', 'url' => '/#catalog']);
        $jaluzi = $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Жалюзи', 'url' => '/zabor-jaluzi/', 'parentId' => $fences]);
        $contacts = $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Контакты', 'url' => '/contacts/']);
        $rancho = $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Ранчо', 'url' => '/gorizont/']);

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/menu/positions/header/order', ['items' => [
            ['id' => $contacts, 'parentId' => null],
            ['id' => $fences, 'parentId' => null],
            ['id' => $rancho, 'parentId' => $fences],
            ['id' => $jaluzi, 'parentId' => $fences],
        ]]);
        self::assertResponseIsSuccessful();

        $tree = $this->provider()->items(MenuPosition::HEADER);
        self::assertSame(['Контакты', 'Заборы'], array_column($tree, 'label'));
        self::assertIsArray($tree[1]['children']);
        self::assertSame(['Ранчо', 'Жалюзи'], array_column($tree[1]['children'], 'label'));

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/menu/positions/header/order', ['items' => [
            ['id' => $contacts, 'parentId' => null],
            ['id' => $fences, 'parentId' => null],
        ]]);
        self::assertResponseStatusCodeSame(422, 'В новом порядке должны быть все пункты позиции.');

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/menu/positions/header/order', ['items' => [
            ['id' => $contacts, 'parentId' => null],
            ['id' => $fences, 'parentId' => null],
            ['id' => $rancho, 'parentId' => $fences],
            ['id' => $jaluzi, 'parentId' => $rancho],
        ]]);
        self::assertResponseStatusCodeSame(422, 'Третий уровень запрещён и в новом порядке.');
    }

    public function testMovingSectionToAnotherPositionCarriesChildrenAndDeleteRemovesThem(): void
    {
        $client = $this->adminClient('menu-move@example.test');
        $section = $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Материалы', 'url' => '/#materials']);
        $this->createItem($client, ['position' => MenuPosition::HEADER, 'label' => 'Профнастил', 'url' => '/fasad/profnastil/', 'parentId' => $section]);

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/menu/items/'.$section, ['position' => MenuPosition::FOOTER, 'label' => 'Материалы', 'url' => '/#materials']);
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->provider()->items(MenuPosition::HEADER));
        $footer = $this->provider()->items(MenuPosition::FOOTER);
        self::assertIsArray($footer[0]['children'] ?? null);
        self::assertSame(['Профнастил'], array_column($footer[0]['children'], 'label'));

        $this->jsonRequestWithCsrf($client, 'DELETE', '/admin/api/menu/items/'.$section);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->items()->findByPosition(MenuPosition::FOOTER), 'Раздел удаляется вместе с подпунктами.');
    }

    private function adminClient(string $email): KernelBrowser
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser($email));

        return $client;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createItem(KernelBrowser $client, array $payload): string
    {
        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/menu/items', $payload);
        self::assertResponseStatusCodeSame(201);

        return $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');
    }

    private function provider(): MenuProvider
    {
        $provider = self::getContainer()->get(MenuProvider::class);
        if (!$provider instanceof MenuProvider) {
            throw new LogicException('MenuProvider service is not available.');
        }

        return $provider;
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

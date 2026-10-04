<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageType;
use App\Tests\Support\Admin\AdminApiTestCase;

final class PageListPaginationApiTest extends AdminApiTestCase
{
    public function testListWithoutPaginationParametersReturnsEveryPage(): void
    {
        $client = $this->adminClient();
        $this->createPages(3);

        $this->api($client, 'GET', '/admin/api/content/pages');

        self::assertResponseIsSuccessful();
        $payload = $this->json($client);
        self::assertCount(3, $this->rows($payload['pages']));
        self::assertArrayNotHasKey('meta', $payload);
    }

    public function testListIsPaginatedOnTheServer(): void
    {
        $client = $this->adminClient();
        $this->createPages(5);

        $this->api($client, 'GET', '/admin/api/content/pages?page=2&perPage=2');

        self::assertResponseIsSuccessful();
        $payload = $this->json($client);
        self::assertCount(2, $this->rows($payload['pages']));
        self::assertSame(['total' => 5, 'page' => 2, 'perPage' => 2, 'pages' => 3], $payload['meta']);

        $this->api($client, 'GET', '/admin/api/content/pages?page=3&perPage=2');
        self::assertCount(1, $this->rows($this->json($client)['pages']));
    }

    public function testListFiltersByQueryAndStatus(): void
    {
        $client = $this->adminClient();
        $this->createPages(3);
        $published = new Page(PageType::Landing, 'Ворота откатные', 'vorota', '/vorota/', 'Ворота');
        $published->publish();
        $this->entityManager()->persist($published);
        $this->entityManager()->flush();

        $this->api($client, 'GET', '/admin/api/content/pages?page=1&q=vorota');
        $payload = $this->json($client);
        self::assertSame(1, $this->meta($payload)['total']);

        $this->api($client, 'GET', '/admin/api/content/pages?page=1&status=published');
        self::assertSame(1, $this->meta($this->json($client))['total']);

        $this->api($client, 'GET', '/admin/api/content/pages?page=1&status=draft');
        self::assertSame(3, $this->meta($this->json($client))['total']);

        $this->api($client, 'GET', '/admin/api/content/pages?page=1&q=%25');
        self::assertSame(0, $this->meta($this->json($client))['total']);
    }

    public function testPerPageIsCapped(): void
    {
        $client = $this->adminClient();
        $this->createPages(1);

        $this->api($client, 'GET', '/admin/api/content/pages?page=1&perPage=100000');

        self::assertSame(100, $this->meta($this->json($client))['perPage']);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function meta(array $payload): array
    {
        self::assertIsArray($payload['meta']);

        $meta = [];
        foreach ($payload['meta'] as $key => $value) {
            $meta[(string) $key] = $value;
        }

        return $meta;
    }

    private function createPages(int $count): void
    {
        for ($index = 1; $index <= $count; ++$index) {
            $this->entityManager()->persist(new Page(PageType::Landing, 'Страница '.$index, 'page-'.$index, '/page-'.$index.'/', 'Страница '.$index));
        }

        $this->entityManager()->flush();
    }
}

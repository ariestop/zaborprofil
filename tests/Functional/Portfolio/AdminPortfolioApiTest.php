<?php

declare(strict_types=1);

namespace App\Tests\Functional\Portfolio;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\User\Domain\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminPortfolioApiTest extends WebTestCase
{
    private const string RUTUBE = 'https://rutube.ru/video/0123456789abcdef0123456789abcdef/';

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testEditorCreatesCategoryAndProjectWithMediaThenPublishes(): void
    {
        $client = $this->client('ROLE_EDITOR');
        $category = $this->create($client, '/admin/api/portfolio/categories', ['title' => 'Заборы Жалюзи', 'slug' => 'zabor-zhalyuzi', 'shortTitle' => 'Жалюзи']);

        $project = $this->create($client, '/admin/api/portfolio/projects', $this->project($category, [
            'description' => '<p>🏡 Двор закрыт от дороги <script>alert(1)</script><img src=x onerror=alert(1)></p><ul><li>✅ Замер за день</li></ul>',
            'media' => [
                ['kind' => 'embed', 'src' => self::RUTUBE, 'caption' => 'Обзор'],
                ['kind' => 'photo', 'src' => '/uploads/media/a.jpg'],
                ['kind' => 'photo', 'src' => '/uploads/media/b.jpg', 'isCover' => true],
                ['kind' => 'video', 'src' => '/uploads/media/c.mp4', 'poster' => '/uploads/media/a.jpg'],
            ],
        ]));
        $data = $this->get($client, '/admin/api/portfolio/projects/'.$project);

        self::assertSame('draft', $data['status']);
        self::assertSame('/portfolio/zabor-zhalyuzi/zhalyuzi-grafit/', $data['path']);
        self::assertSame('/uploads/media/b.jpg', $data['cover']);
        self::assertSame(2, $data['photoCount']);
        self::assertSame(2, $data['videoCount']);
        $media = $data['media'];
        self::assertIsArray($media);
        self::assertIsArray($media[0]);
        self::assertSame('https://rutube.ru/play/embed/0123456789abcdef0123456789abcdef/', $media[0]['src'], 'Ссылка Rutube приведена к адресу плеера.');
        $description = $data['description'];
        self::assertIsString($description);
        self::assertStringContainsString('🏡 Двор закрыт от дороги', $description, 'Эмодзи сохраняются.');
        self::assertStringContainsString('<li>✅ Замер за день</li>', $description);
        self::assertStringNotContainsString('script', $description);
        self::assertStringNotContainsString('onerror', $description);
        self::assertSame([['label' => 'Длина', 'value' => '42 м'], ['label' => 'Высота', 'value' => '1,8 м']], $data['specs']);

        $this->send($client, 'PUT', '/admin/api/portfolio/projects/'.$project.'/status', ['published' => true]);
        self::assertResponseIsSuccessful();
        self::assertSame('published', $this->get($client, '/admin/api/portfolio/projects/'.$project)['status']);

        $list = $this->get($client, '/admin/api/portfolio');
        self::assertIsArray($list['categories']);
        self::assertIsArray($list['projects']);
        self::assertCount(1, $list['projects']);
        self::assertIsArray($list['projects'][0]);
        self::assertArrayNotHasKey('media', $list['projects'][0], 'Список работ — без медиа и описания.');
        self::assertSame('/uploads/media/b.jpg', $list['projects'][0]['cover']);
    }

    public function testValidationErrorsAreReadable(): void
    {
        $client = $this->client('ROLE_ADMIN');
        $category = $this->create($client, '/admin/api/portfolio/categories', ['title' => 'Профнастил', 'slug' => 'profnastil']);

        $cases = [
            [['media' => [['kind' => 'embed', 'src' => 'https://youtube.com/watch?v=1']]], 'VK или Rutube'],
            [['media' => [['kind' => 'photo', 'src' => 'https://evil.example/a.jpg']]], 'из медиатеки'],
            [['slug' => 'Не латиница'], 'латиница'],
            [['title' => ' '], 'название работы'],
            [['specs' => [['label' => 'Длина', 'value' => '']]], 'и название, и значение'],
        ];
        foreach ($cases as [$override, $message]) {
            $this->send($client, 'POST', '/admin/api/portfolio/projects', $this->project($category, $override));
            self::assertResponseStatusCodeSame(422);
            self::assertStringContainsString($message, $this->error($client));
        }

        $first = $this->create($client, '/admin/api/portfolio/projects', $this->project($category));
        $this->send($client, 'POST', '/admin/api/portfolio/projects', $this->project($category));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('уже есть работа с таким адресом', $this->error($client));

        $this->send($client, 'PUT', '/admin/api/portfolio/projects/'.$first.'/status', ['published' => true]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('хотя бы одно фото', $this->error($client));

        $this->send($client, 'DELETE', '/admin/api/portfolio/categories/'.$category);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('В категории есть работы', $this->error($client));

        $this->send($client, 'POST', '/admin/api/portfolio/categories', ['title' => 'Дубль', 'slug' => 'profnastil']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testReorderAndDelete(): void
    {
        $client = $this->client('ROLE_ADMIN');
        $a = $this->create($client, '/admin/api/portfolio/categories', ['title' => 'А', 'slug' => 'a']);
        $b = $this->create($client, '/admin/api/portfolio/categories', ['title' => 'Б', 'slug' => 'b']);
        $p1 = $this->create($client, '/admin/api/portfolio/projects', $this->project($a, ['slug' => 'one', 'title' => 'Первая']));
        $p2 = $this->create($client, '/admin/api/portfolio/projects', $this->project($a, ['slug' => 'two', 'title' => 'Вторая']));

        $this->send($client, 'PUT', '/admin/api/portfolio/categories-order', ['ids' => [$b, $a]]);
        self::assertResponseIsSuccessful();
        $this->send($client, 'PUT', '/admin/api/portfolio/categories/'.$a.'/projects-order', ['ids' => [$p2, $p1]]);
        self::assertResponseIsSuccessful();
        $this->send($client, 'PUT', '/admin/api/portfolio/categories/'.$a.'/projects-order', ['ids' => [$p2]]);
        self::assertResponseStatusCodeSame(422, 'Неполный список — ошибка, порядок не меняется.');

        $list = $this->get($client, '/admin/api/portfolio');
        self::assertIsArray($list['categories']);
        self::assertSame(['Б', 'А'], array_map(static fn (mixed $c): mixed => \is_array($c) ? $c['title'] : null, $list['categories']));
        self::assertIsArray($list['projects']);
        self::assertSame(['Вторая', 'Первая'], array_map(static fn (mixed $p): mixed => \is_array($p) ? $p['title'] : null, $list['projects']));

        // Перенос работы в другую категорию — в конец её списка.
        $this->send($client, 'PUT', '/admin/api/portfolio/projects/'.$p1, $this->project($b, ['slug' => 'one', 'title' => 'Первая']));
        self::assertResponseIsSuccessful();
        self::assertSame('/portfolio/b/one/', $this->get($client, '/admin/api/portfolio/projects/'.$p1)['path']);

        $this->send($client, 'DELETE', '/admin/api/portfolio/projects/'.$p2);
        self::assertResponseStatusCodeSame(204);
        $this->send($client, 'DELETE', '/admin/api/portfolio/categories/'.$a);
        self::assertResponseStatusCodeSame(204);
    }

    public function testSeoCanViewButNotEditAndManagerCannotView(): void
    {
        $client = $this->client('ROLE_SEO');
        $client->request('GET', '/admin/api/portfolio');
        self::assertResponseIsSuccessful();
        $this->send($client, 'POST', '/admin/api/portfolio/categories', ['title' => 'А', 'slug' => 'a']);
        self::assertResponseStatusCodeSame(403);

        self::ensureKernelShutdown();
        $client = $this->client('ROLE_MANAGER');
        $client->request('GET', '/admin/api/portfolio');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function project(string $categoryId, array $override = []): array
    {
        return array_replace([
            'categoryId' => $categoryId,
            'title' => 'Жалюзи графит на цоколе',
            'slug' => 'zhalyuzi-grafit',
            'place' => 'Саратов',
            'year' => 2025,
            'summary' => 'Забор Жалюзи на цоколе из габионов',
            'description' => '<p>Текст</p>',
            'specs' => [['label' => 'Длина', 'value' => '42 м'], ['label' => 'Высота', 'value' => '1,8 м']],
            'media' => [],
        ], $override);
    }

    private function client(string $role): KernelBrowser
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $user = new AdminUser(strtolower($role).'@example.test', 'hash', [$role]);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();
        $client->loginUser($user);

        return $client;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function create(KernelBrowser $client, string $uri, array $payload): string
    {
        $this->send($client, 'POST', $uri, $payload);
        self::assertResponseStatusCodeSame(201, (string) $client->getResponse()->getContent());
        $id = $this->decode($client)['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function get(KernelBrowser $client, string $uri): array
    {
        $client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        return $this->decode($client);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(KernelBrowser $client): array
    {
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    private function error(KernelBrowser $client): string
    {
        $error = $this->decode($client)['error'] ?? null;
        self::assertIsString($error);

        return $error;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(KernelBrowser $client, string $method, string $uri, array $payload = []): void
    {
        $client->request('GET', '/admin/dashboard');
        $html = (string) $client->getResponse()->getContent();
        if (!preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', $html, $matches)) {
            throw new LogicException('Admin CSRF token meta tag was not rendered.');
        }

        $client->jsonRequest($method, $uri, $payload, [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $matches[1],
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ]);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }
}

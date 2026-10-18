<?php

declare(strict_types=1);

namespace App\Tests\Functional\Portfolio;

use App\Module\Portfolio\Application\Command\PortfolioCategoryInput;
use App\Module\Portfolio\Application\Command\PortfolioMediaInput;
use App\Module\Portfolio\Application\Command\PortfolioProjectInput;
use App\Module\Portfolio\Application\Service\PortfolioAdminService;
use App\Module\Portfolio\Application\Service\PortfolioLikeService;
use App\Module\Portfolio\UI\Web\PortfolioLikeController;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class PortfolioLikeApiTest extends WebTestCase
{
    private KernelBrowser $client;

    private string $published;

    private string $draft;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        SchemaTestHelper::recreateSchema($entityManager);
        // Лимиты rate limiter в тестах живут в памяти контейнера: без перезагрузки ядра они копятся между запросами теста.
        $this->client->disableReboot();

        $service = self::getContainer()->get(PortfolioAdminService::class);
        self::assertInstanceOf(PortfolioAdminService::class, $service);
        $category = (string) $service->createCategory(new PortfolioCategoryInput('Заборы Жалюзи', 'zabor-jaluzi'))->id();
        $ids = [];
        foreach (['grafit', 'chernovik'] as $slug) {
            $ids[$slug] = (string) $service->createProject(new PortfolioProjectInput(
                $category,
                'Работа '.$slug,
                $slug,
                null,
                null,
                null,
                '',
                [],
                [new PortfolioMediaInput('photo', '/uploads/media/'.$slug.'.jpg', null, null)],
            ))->id();
        }
        $service->setPublished($ids['grafit'], true);
        $this->published = $ids['grafit'];
        $this->draft = $ids['chernovik'];
    }

    public function testLikeIsCountedOncePerVisitorAndRemembered(): void
    {
        self::assertSame(['count' => 1, 'liked' => true], $this->like($this->published, true));
        $cookie = $this->client->getCookieJar()->get(PortfolioLikeController::VISITOR_COOKIE);
        self::assertNotNull($cookie, 'Посетитель получает cookie.');
        self::assertTrue($cookie->isHttpOnly());
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $cookie->getValue());

        self::assertSame(['count' => 1, 'liked' => true], $this->like($this->published, true), 'Повторный лайк не накручивает счётчик.');
        self::assertSame(['count' => 1, 'liked' => true], $this->states([$this->published])[$this->published], 'Сервер помнит лайк посетителя.');

        self::assertSame(['count' => 0, 'liked' => false], $this->like($this->published, false), 'Повторное нажатие снимает лайк.');
        self::assertSame(['count' => 0, 'liked' => false], $this->like($this->published, false));
    }

    public function testStoresOnlyHashesAndCountsOtherVisitors(): void
    {
        $this->like($this->published, true);
        $token = $this->client->getCookieJar()->get(PortfolioLikeController::VISITOR_COOKIE)?->getValue();
        self::assertIsString($token);

        $this->client->getCookieJar()->clear();
        self::assertSame(['count' => 2, 'liked' => true], $this->like($this->published, true), 'Другой посетитель — ещё один лайк.');
        $this->client->getCookieJar()->clear();
        self::assertSame(['count' => 2, 'liked' => false], $this->states([$this->published])[$this->published] ?? null, 'Без cookie «мой лайк» не отмечен.');

        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $rows = $connection->fetchAllAssociative('SELECT visitor_hash, ip_hash FROM portfolio_likes');
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertIsString($row['visitor_hash']);
            self::assertIsString($row['ip_hash']);
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $row['visitor_hash']);
            self::assertNotSame($token, $row['visitor_hash']);
            self::assertStringNotContainsString('127.0.0.1', $row['ip_hash']);
        }
    }

    public function testNewCookiesFromOneNetworkHitTheLimit(): void
    {
        for ($i = 1; $i <= PortfolioLikeService::MAX_LIKES_PER_IP; ++$i) {
            $this->client->getCookieJar()->clear();
            self::assertSame($i, $this->like($this->published, true)['count'] ?? null);
        }

        $this->client->getCookieJar()->clear();
        $this->post($this->published, ['liked' => true]);
        self::assertResponseStatusCodeSame(429);
        self::assertSame('LIKE_LIMIT', $this->json()['code'] ?? null);
        self::assertSame(PortfolioLikeService::MAX_LIKES_PER_IP, $this->states([$this->published])[$this->published]['count'] ?? null);
    }

    public function testRejectsForeignSitesDraftsAndBadInput(): void
    {
        $this->client->request('POST', '/api/portfolio/likes/'.$this->published, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'https://evil.example'], content: '{"liked":true}');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/api/portfolio/likes/'.$this->published, server: ['CONTENT_TYPE' => 'application/json'], content: '{"liked":true}');
        self::assertResponseStatusCodeSame(403, 'Без Origin лайк не принимается.');
        $this->client->request('POST', '/api/portfolio/likes/'.$this->published, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost', 'HTTP_SEC_FETCH_SITE' => 'cross-site'], content: '{"liked":true}');
        self::assertResponseStatusCodeSame(403);

        $this->post($this->draft, ['liked' => true]);
        self::assertResponseStatusCodeSame(404);
        $this->post($this->published, ['liked' => 'yes']);
        self::assertResponseStatusCodeSame(422);

        self::assertSame([$this->published], array_keys($this->states([$this->published, $this->draft, 'nope'])), 'Черновики и мусор в ответ не попадают.');
        self::assertSame(['count' => 0, 'liked' => false], $this->states([$this->published])[$this->published]);
    }

    public function testFrequentClicksAreRateLimited(): void
    {
        $this->client->getCookieJar()->set(new Cookie(PortfolioLikeController::VISITOR_COOKIE, str_repeat('a', 32)));
        for ($i = 0; $i < 60; ++$i) {
            $this->post($this->published, ['liked' => $i % 2 === 0]);
            self::assertResponseIsSuccessful();
        }
        $this->post($this->published, ['liked' => true]);
        self::assertResponseStatusCodeSame(429);
        self::assertSame('TOO_MANY_REQUESTS', $this->json()['code'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function like(string $id, bool $liked): array
    {
        $this->post($id, ['liked' => $liked]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        return $this->json();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $id, array $payload): void
    {
        $this->client->request('POST', '/api/portfolio/likes/'.$id, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => 'http://localhost',
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, array{count: int, liked: bool}>
     */
    private function states(array $ids): array
    {
        $this->client->request('GET', '/api/portfolio/likes?ids='.implode(',', $ids));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $likes = $this->json()['likes'] ?? null;
        self::assertIsArray($likes);

        /** @var array<string, array{count: int, liked: bool}> $likes */
        return $likes;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $data;
    }
}

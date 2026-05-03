<?php

declare(strict_types=1);

namespace App\Tests\Functional\Settings;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\Content\Application\Service\PublicPageCacheKey;
use App\Module\Content\Application\Service\PublicPagePathNormalizer;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class AdminSettingsApiTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testAdminCanCreateAndListSettings(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('settings@example.test'));

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/settings/site/name', [
            'value' => 'ЗаборПрофиль',
            'description' => 'Название сайта',
        ]);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/api/settings?scope=site');
        self::assertResponseIsSuccessful();

        $payload = json_decode($client->getResponse()->getContent() ?: '[]', true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertCount(1, $payload);
        self::assertIsArray($payload[0]);
        self::assertSame('site', $payload[0]['scope'] ?? null);
        self::assertSame('name', $payload[0]['key'] ?? null);
        self::assertSame('ЗаборПрофиль', $payload[0]['value'] ?? null);
    }

    public function testUpdatingSettingsInvalidatesPublicPageCache(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('settings-cache@example.test'));

        $key = PublicPageCacheKey::forPath(PublicPagePathNormalizer::normalize('/settings-cache/'));
        $this->tagAwareCache($client)->get($key, static function (ItemInterface $item): string {
            $item->tag([PublicPageCacheKey::globalTag()]);

            return 'cached';
        });
        self::assertTrue($this->cacheItemPool($client)->getItem($key)->isHit());

        $this->jsonRequestWithCsrf($client, 'PUT', '/admin/api/settings/site/name', [
            'value' => 'ЗаборПрофиль',
            'description' => 'Название сайта',
        ]);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->cacheItemPool($client)->getItem($key)->isHit());
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

    private function tagAwareCache(KernelBrowser $client): TagAwareCacheInterface
    {
        $pool = $client->getContainer()->get('cache.public_page');

        if (!$pool instanceof TagAwareCacheInterface) {
            throw new LogicException('cache.public_page pool service is not available.');
        }

        return $pool;
    }

    private function cacheItemPool(KernelBrowser $client): CacheItemPoolInterface
    {
        $pool = $client->getContainer()->get('cache.public_page');

        if (!$pool instanceof CacheItemPoolInterface) {
            throw new LogicException('cache.public_page pool service is not available.');
        }

        return $pool;
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

        $token = $matches[1];

        $client->jsonRequest($method, $uri, $payload, [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $token,
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ]);
    }
}

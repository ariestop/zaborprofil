<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\Content\Application\Service\PublicPageCacheKey;
use App\Module\Content\Application\Service\PublicPagePathNormalizer;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class RedirectControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testActiveRedirectReturnsConfiguredResponse(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $this->redirectRepository()->save(new Redirect('/old-page/', '/new-page/', 301));

        $client->request('GET', '/old-page/');

        self::assertResponseRedirects('/new-page/', 301);
    }

    public function testPagePathChangeCreatesRedirect(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $pageRepository = $this->pageRepository();
        $page = new Page(PageType::Landing, 'Заборы', 'fences', '/old-fences/', 'Заборы');
        $pageRepository->save($page);

        $page->update(PageType::Landing, 'Заборы', 'fences', '/new-fences/', 'Заборы', 'default', 0, true);
        $pageRepository->save($page);

        $client->request('GET', '/old-fences/');

        self::assertResponseRedirects('/new-fences/', 301);
    }

    public function testUpdatingRedirectInvalidatesPublicPageCache(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $client->loginUser($this->createAdminUser('redirect-cache@example.test'));

        $key = PublicPageCacheKey::forPath(PublicPagePathNormalizer::normalize('/redirect-cache/'));
        $this->tagAwareCache($client)->get($key, static function (ItemInterface $item): string {
            $item->tag([PublicPageCacheKey::globalTag()]);

            return 'cached';
        });
        self::assertTrue($this->cacheItemPool($client)->getItem($key)->isHit());

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/seo/redirects', [
            'sourcePath' => '/redirect-cache-old/',
            'targetPath' => '/redirect-cache/',
            'statusCode' => 301,
            'isActive' => true,
        ]);

        self::assertResponseStatusCodeSame(201);
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

    private function redirectRepository(): RedirectRepositoryInterface
    {
        $repository = self::getContainer()->get(RedirectRepositoryInterface::class);

        if (!$repository instanceof RedirectRepositoryInterface) {
            throw new LogicException('Redirect repository service is not available.');
        }

        return $repository;
    }

    private function pageRepository(): PageRepositoryInterface
    {
        $repository = self::getContainer()->get(PageRepositoryInterface::class);

        if (!$repository instanceof PageRepositoryInterface) {
            throw new LogicException('Page repository service is not available.');
        }

        return $repository;
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

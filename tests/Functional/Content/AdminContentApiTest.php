<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

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

final class AdminContentApiTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testAdminCanCreatePublishAndRenderPage(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('admin@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Забор жалюзи',
            'slug' => 'zabor-jaluzi',
            'path' => '/zabor-jaluzi/',
            'h1' => 'Забор жалюзи',
            'template' => 'landing',
        ]);

        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse($client->getResponse()->getContent() ?: '', 'id');

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/blocks', $pageId), [
            'type' => 'hero',
            'name' => 'Главный экран',
            'position' => 0,
            'content' => ['title' => 'Забор жалюзи под ключ', 'text' => 'Производство и монтаж.'],
            'settings' => [],
            'isEnabled' => true,
        ]);

        self::assertResponseStatusCodeSame(201);

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/zabor-jaluzi/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Забор жалюзи');
        self::assertSelectorTextContains('h2', 'Забор жалюзи под ключ');

        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('aria-label="Хлебные крошки"', $html);
        self::assertStringContainsString('Забор жалюзи</span>', $html);
        self::assertStringContainsString('"@type":"BreadcrumbList"', $html);
    }

    public function testDraftPageIsNotPubliclyVisible(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('editor@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Черновик',
            'slug' => 'draft-page',
            'path' => '/draft-page/',
            'h1' => 'Черновик',
        ]);

        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/draft-page/');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminCanUpdateSeoMetadataAndRendersTagsOnPublicPage(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('seo@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Заборы под ключ',
            'slug' => 'zabory-pod-kluch',
            'path' => '/zabory-pod-kluch/',
            'h1' => 'Заборы под ключ',
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), [
            'metaDescription' => 'Производство и установка заборов под ключ в Москве и области.',
            'canonicalUrl' => 'https://zaborprofil.test/zabory-pod-kluch/',
            'ogTitle' => 'Заборы под ключ — ЗаборПрофиль',
            'ogDescription' => 'Заводское качество, гарантия 5 лет.',
            'ogImage' => 'https://zaborprofil.test/og/zabory.jpg',
            'ogType' => 'website',
            'jsonLd' => [
                ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'Забор под ключ'],
            ],
        ]);
        self::assertResponseIsSuccessful();

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/zabory-pod-kluch/');
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('<meta name="description" content="Производство и установка заборов под ключ в Москве и области.">', $html);
        self::assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        self::assertStringContainsString('<link rel="canonical" href="https://zaborprofil.test/zabory-pod-kluch/">', $html);
        self::assertStringContainsString('<meta property="og:title" content="Заборы под ключ — ЗаборПрофиль">', $html);
        self::assertStringContainsString('<meta property="og:description" content="Заводское качество, гарантия 5 лет.">', $html);
        self::assertStringContainsString('<meta property="og:image" content="https://zaborprofil.test/og/zabory.jpg">', $html);
        self::assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        self::assertMatchesRegularExpression('#"@context":"https:\\\\?/\\\\?/schema\.org"#', $html);
        self::assertStringContainsString('"@type":"WebPage"', $html);
        self::assertStringContainsString('"url":"https:\/\/zaborprofil.test\/zabory-pod-kluch\/"', $html);
        self::assertStringContainsString('"@type":"Product"', $html);
    }

    public function testNonIndexablePageRendersNoindexRobots(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('noidx@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Промо страница',
            'slug' => 'promo',
            'path' => '/promo/',
            'h1' => 'Промо',
            'isIndexable' => false,
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/promo/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', (string) $client->getResponse()->getContent());
    }

    public function testPublicPageWithoutSeoMetadataFallsBackToCanonicalFromPath(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('default-seo@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Без SEO',
            'slug' => 'no-seo',
            'path' => '/no-seo/',
            'h1' => 'Без SEO',
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/no-seo/');
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<link rel="canonical" href="', $html);
        self::assertStringContainsString('/no-seo/', $html);
        self::assertStringNotContainsString('<meta name="description"', $html);
    }

    public function testCanonicalUrlMustBelongToSiteHost(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('canonical@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Canonical guard',
            'slug' => 'canonical-guard',
            'path' => '/canonical-guard/',
            'h1' => 'Canonical guard',
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), [
            'canonicalUrl' => 'https://example.com/canonical-guard/',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Canonical URL must belong to the configured SITE_URL host.', (string) $client->getResponse()->getContent());
    }

    public function testSeoAuditApiReportsPageWarnings(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('seo-audit@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'SEO Audit Page',
            'slug' => 'seo-audit',
            'path' => '/seo-audit/',
            'h1' => 'SEO Audit Page',
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'GET', \sprintf('/admin/api/seo/audit/pages/%s', $pageId));

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertTrue($payload['passed'] ?? false);
        self::assertIsArray($payload['issues'] ?? null);
        self::assertNotEmpty($payload['issues']);
    }

    public function testPrePublishChecklistBlocksReservedPagePath(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('prepublish@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Reserved Path',
            'slug' => 'reserved-path',
            'path' => '/admin/reserved/',
            'h1' => 'Reserved Path',
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Pre-publish SEO checklist failed: seo.path.reserved_prefix', (string) $client->getResponse()->getContent());
    }

    public function testPublishingAPageInvalidatesPublicPageCache(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $this->cachePool($client)->clear();
        $client->loginUser($this->createAdminUser('cache@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Cached invalidation',
            'slug' => 'cached-inv',
            'path' => '/cached-inv/',
            'h1' => 'Cached',
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/cached-inv/');
        self::assertResponseIsSuccessful();

        $key = PublicPageCacheKey::forPath(PublicPagePathNormalizer::normalize('/cached-inv/'));
        self::assertTrue(
            $this->cachePool($client)->getItem($key)->isHit(),
            'Public page request must populate the cache.public_page pool.',
        );

        $this->jsonRequestWithCsrf($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), [
            'metaDescription' => 'Updated description triggers cache invalidation',
        ]);
        self::assertResponseIsSuccessful();

        self::assertFalse(
            $this->cachePool($client)->getItem($key)->isHit(),
            'UpdatePageSeoMetadataHandler must invalidate the public page cache for the page path.',
        );
    }

    public function testDraftPageCanBePreviewedWithNoindexHeader(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('preview@example.test'));

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Preview draft',
            'slug' => 'preview-draft',
            'path' => '/preview-draft/',
            'h1' => 'Preview draft',
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'id');

        $this->jsonRequestWithCsrf($client, 'GET', \sprintf('/admin/api/content/pages/%s/preview-link', $pageId));
        self::assertResponseIsSuccessful();
        $previewUrl = $this->stringFromResponse((string) $client->getResponse()->getContent(), 'previewUrl');

        $client->request('GET', $previewUrl);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex,nofollow');
        self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', (string) $client->getResponse()->getContent());
        self::assertSelectorTextContains('h1', 'Preview draft');
    }

    public function testAdminApiRejectsRequestWithoutCsrfToken(): void
    {
        $client = self::createClient();
        $this->prepareDatabase();
        $client->loginUser($this->createAdminUser('csrf@example.test'));

        $client->jsonRequest('POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'No CSRF',
            'slug' => 'no-csrf',
            'path' => '/no-csrf/',
            'h1' => 'No CSRF',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    private function prepareDatabase(): void
    {
        SchemaTestHelper::recreateSchema($this->entityManager());
    }

    private function createAdminUser(string $email): AdminUser
    {
        $entityManager = $this->entityManager();
        $user = new AdminUser($email, 'hash', ['ROLE_ADMIN']);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function cachePool(KernelBrowser $client): CacheItemPoolInterface
    {
        $pool = $client->getContainer()->get('cache.public_page');

        if (!$pool instanceof CacheItemPoolInterface) {
            throw new LogicException('cache.public_page pool service is not available.');
        }

        return $pool;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }

    private function stringFromResponse(string $content, string $key): string
    {
        $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        if (!\is_array($payload) || !isset($payload[$key]) || !\is_string($payload[$key])) {
            throw new LogicException(\sprintf('Response field "%s" must be a string.', $key));
        }

        return $payload[$key];
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

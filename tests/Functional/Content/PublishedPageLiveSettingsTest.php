<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\User\Domain\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Опубликованная страница и правки её настроек без переопубликации.
 *
 * Адрес, индексация и видимость действуют сразу: по новому адресу страница открывается (со старого — 301),
 * и canonical, robots и sitemap должны говорить то же самое, а не повторять снимок последней публикации.
 */
final class PublishedPageLiveSettingsTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testPathChangeOfPublishedPageMovesCanonicalAndBreadcrumbsToNewAddress(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPublishedPage($client, 'live-path-old');

        $this->updatePage($client, $pageId, 'live-path-old', ['path' => '/live-path-new/']);

        $client->request('GET', '/live-path-old/');
        self::assertResponseRedirects('/live-path-new/', 301);

        $client->request('GET', '/live-path-new/');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertMatchesRegularExpression('#<link rel="canonical" href="https?://[^"/]+/live-path-new/">#', $html);
        self::assertMatchesRegularExpression('#<meta property="og:url" content="https?://[^"/]+/live-path-new/">#', $html);
        self::assertStringNotContainsString('/live-path-old/', $html, 'Ни canonical, ни крошки, ни JSON-LD не должны ссылаться на старый адрес.');
    }

    public function testIndexableChangeOfPublishedPageAppliesToRobotsAndSitemapTogether(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPublishedPage($client, 'live-indexable');

        $this->updatePage($client, $pageId, 'live-indexable', ['isIndexable' => false]);
        $this->assertRobots($client, '/live-indexable/', 'noindex, nofollow');
        $this->assertInSitemap($client, '/live-indexable/', false);

        $this->updatePage($client, $pageId, 'live-indexable', ['isIndexable' => true]);
        $this->assertRobots($client, '/live-indexable/', 'index, follow');
        $this->assertInSitemap($client, '/live-indexable/', true);
    }

    public function testHiddenPublishedPageIsNotAvailablePubliclyNorInSitemap(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPublishedPage($client, 'live-hidden');
        $this->assertInSitemap($client, '/live-hidden/', true);

        $this->updatePage($client, $pageId, 'live-hidden', ['visibility' => 'hidden']);

        $client->request('GET', '/live-hidden/');
        self::assertResponseStatusCodeSame(404);
        $this->assertInSitemap($client, '/live-hidden/', false);

        // Предпросмотр для редакторов работает и у скрытой страницы.
        $client->request('GET', \sprintf('/admin/api/content/pages/%s/preview-link', $pageId));
        self::assertResponseIsSuccessful();
        $previewUrl = $this->stringFromResponse($client, 'previewUrl');
        $client->request('GET', $previewUrl);
        self::assertResponseIsSuccessful();

        $this->updatePage($client, $pageId, 'live-hidden', ['visibility' => 'public']);
        $client->request('GET', '/live-hidden/');
        self::assertResponseIsSuccessful();
    }

    public function testHiddenParentIsNotLinkedInBreadcrumbsOfChildPage(): void
    {
        $client = $this->adminClient();
        $parentId = $this->createPublishedPage($client, 'live-parent');
        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'Дочерняя',
            'slug' => 'child',
            'path' => '/live-parent/child/',
            'h1' => 'Дочерняя',
        ]);
        self::assertResponseStatusCodeSame(201);
        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $this->stringFromResponse($client, 'id')));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/live-parent/child/');
        self::assertStringContainsString('href="/live-parent/"', (string) $client->getResponse()->getContent());

        $this->updatePage($client, $parentId, 'live-parent', ['visibility' => 'hidden']);

        $client->request('GET', '/live-parent/child/');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('href="/live-parent/"', (string) $client->getResponse()->getContent());
    }

    public function testUnlistedPublishedPageOpensByLinkButIsNotIndexed(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPublishedPage($client, 'live-unlisted');

        $this->updatePage($client, $pageId, 'live-unlisted', ['visibility' => 'unlisted']);

        $this->assertRobots($client, '/live-unlisted/', 'noindex, nofollow');
        $this->assertInSitemap($client, '/live-unlisted/', false);
    }

    public function testPageVisibilityIsHiddenFromSitemapEvenBeforeAnyChangeAfterPublication(): void
    {
        $client = $this->adminClient();
        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => 'live-unlisted-from-start',
            'slug' => 'live-unlisted-from-start',
            'path' => '/live-unlisted-from-start/',
            'h1' => 'live-unlisted-from-start',
            'visibility' => 'unlisted',
        ]);
        self::assertResponseStatusCodeSame(201);
        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $this->stringFromResponse($client, 'id')));
        self::assertResponseIsSuccessful();

        $this->assertRobots($client, '/live-unlisted-from-start/', 'noindex, nofollow');
        $this->assertInSitemap($client, '/live-unlisted-from-start/', false);
    }

    private function assertRobots(KernelBrowser $client, string $path, string $expected): void
    {
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(\sprintf('<meta name="robots" content="%s">', $expected), (string) $client->getResponse()->getContent());
    }

    private function assertInSitemap(KernelBrowser $client, string $path, bool $expected): void
    {
        $client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        $loc = \sprintf('<loc>https://zaborprofil.test%s</loc>', $path);
        $sitemap = (string) $client->getResponse()->getContent();
        $expected ? self::assertStringContainsString($loc, $sitemap) : self::assertStringNotContainsString($loc, $sitemap);
    }

    private function adminClient(): KernelBrowser
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $user = new AdminUser('live-settings@example.test', 'hash', ['ROLE_ADMIN']);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();
        $client->loginUser($user);

        return $client;
    }

    private function createPublishedPage(KernelBrowser $client, string $slug): string
    {
        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => $slug,
            'slug' => $slug,
            'path' => \sprintf('/%s/', $slug),
            'h1' => $slug,
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse($client, 'id');

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));
        self::assertResponseIsSuccessful();

        return $pageId;
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function updatePage(KernelBrowser $client, string $pageId, string $slug, array $changes): void
    {
        $this->jsonRequestWithCsrf($client, 'PUT', \sprintf('/admin/api/content/pages/%s', $pageId), [
            'type' => 'landing',
            'title' => $slug,
            'slug' => $slug,
            'path' => \sprintf('/%s/', $slug),
            'h1' => $slug,
            ...$changes,
        ]);
        self::assertResponseIsSuccessful();
    }

    private function stringFromResponse(KernelBrowser $client, string $key): string
    {
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $value = $payload[$key] ?? null;
        self::assertIsString($value);

        return $value;
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
        if (!preg_match('/<meta name="admin-csrf-token" content="([^"]+)">/', (string) $client->getResponse()->getContent(), $matches)) {
            throw new LogicException('Admin CSRF token meta tag was not rendered.');
        }

        $client->jsonRequest($method, $uri, $payload, [
            'HTTP_'.str_replace('-', '_', strtoupper(AdminApiCsrfSubscriber::HEADER_NAME)) => $matches[1],
            'HTTP_ORIGIN' => 'https://zaborprofil.test',
        ]);
    }
}

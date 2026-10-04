<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MetaTitleTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testExplicitMetaTitleIsRenderedInTitleAndOgTitle(): void
    {
        $client = $this->clientWithAdmin('meta-title@example.test');
        $pageId = $this->createPage($client, 'Заборы под ключ', '/zabory/');

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), [
            'metaTitle' => 'Заборы под ключ в Москве — цены и монтаж | ЗаборПрофиль',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('Заборы под ключ в Москве — цены и монтаж | ЗаборПрофиль', $this->metaTitle($client));

        $this->request($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/zabory/');
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('<title>Заборы под ключ в Москве — цены и монтаж | ЗаборПрофиль</title>', $html);
        self::assertStringContainsString('<meta property="og:title" content="Заборы под ключ в Москве — цены и монтаж | ЗаборПрофиль">', $html);
        self::assertSelectorTextContains('h1', 'Заборы под ключ');
    }

    public function testOgTitleOverridesMetaTitleOnlyForOpenGraph(): void
    {
        $client = $this->clientWithAdmin('meta-title-og@example.test');
        $pageId = $this->createPage($client, 'Профнастил', '/profnastil/');

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), [
            'metaTitle' => 'Забор из профнастила',
            'ogTitle' => 'Профнастил для соцсетей',
        ]);
        self::assertResponseIsSuccessful();
        $this->request($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));

        $client->request('GET', '/profnastil/');
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('<title>Забор из профнастила</title>', $html);
        self::assertStringContainsString('<meta property="og:title" content="Профнастил для соцсетей">', $html);
    }

    public function testTitleFallsBackToPageTitleWhenMetaTitleIsEmpty(): void
    {
        $client = $this->clientWithAdmin('meta-title-fallback@example.test');
        $pageId = $this->createPage($client, 'Забор из евроштакетника', '/evroshtaketnik/');

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), ['metaTitle' => '   ']);
        self::assertResponseIsSuccessful();
        $this->assertMetaTitleIsNull($client);

        $this->request($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));

        $client->request('GET', '/evroshtaketnik/');
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('<title>Забор из евроштакетника</title>', $html);
        self::assertStringContainsString('<meta property="og:title" content="Забор из евроштакетника">', $html);
    }

    public function testSeoUpdateWithoutMetaTitleKeepsExistingValue(): void
    {
        $client = $this->clientWithAdmin('meta-title-keep@example.test');
        $pageId = $this->createPage($client, 'Забор сетка', '/zabor-setka/');

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), ['metaTitle' => 'Забор из сетки рабицы']);
        self::assertResponseIsSuccessful();

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), [
            'metaDescription' => 'Описание без изменения SEO title.',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('Забор из сетки рабицы', $this->metaTitle($client));

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), ['metaTitle' => null]);
        self::assertResponseIsSuccessful();
        $this->assertMetaTitleIsNull($client);
    }

    public function testMetaTitleLongerThanLimitIsRejected(): void
    {
        $client = $this->clientWithAdmin('meta-title-long@example.test');
        $pageId = $this->createPage($client, 'Забор штакетник', '/shtaketnik/');

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), ['metaTitle' => str_repeat('я', 256)]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION', $this->json($client)['code'] ?? null);

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), ['metaTitle' => 123]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testDefaultTitleTemplateAppliesOnlyWithoutExplicitMetaTitle(): void
    {
        $client = $this->clientWithAdmin('meta-title-template@example.test');
        $this->request($client, 'PUT', '/admin/api/settings/seo/title_template', ['value' => '{h1} — заборы в Москве | {site_name}']);
        self::assertResponseIsSuccessful();

        $templated = $this->createPage($client, 'Ворота', '/vorota/');
        $explicit = $this->createPage($client, 'Калитки', '/kalitki/');
        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $explicit), ['metaTitle' => 'Калитки с установкой']);

        $this->request($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $templated));
        $this->request($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $explicit));

        $client->request('GET', '/vorota/');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<title>Ворота — заборы в Москве | ЗаборПрофиль</title>', $html);
        self::assertStringContainsString('<meta property="og:title" content="Ворота — заборы в Москве | ЗаборПрофиль">', $html);

        $client->request('GET', '/kalitki/');
        self::assertStringContainsString('<title>Калитки с установкой</title>', (string) $client->getResponse()->getContent());
    }

    public function testRollbackRestoresMetaTitleFromRevision(): void
    {
        $client = $this->clientWithAdmin('meta-title-rollback@example.test');
        $pageId = $this->createPage($client, 'Откат заголовка', '/rollback-title/');

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), ['metaTitle' => 'Старый SEO title страницы']);
        $this->request($client, 'POST', \sprintf('/admin/api/content/pages/%s/builder/publish', $pageId));
        self::assertResponseIsSuccessful();

        $this->request($client, 'GET', \sprintf('/admin/api/content/pages/%s/builder/versions', $pageId));
        $revisions = $this->json($client)['revisions'] ?? null;
        self::assertIsArray($revisions);
        $first = $revisions[0] ?? null;
        self::assertIsArray($first);
        $revisionId = $first['id'] ?? null;
        self::assertIsString($revisionId);

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $pageId), ['metaTitle' => 'Новый SEO title страницы']);
        self::assertResponseIsSuccessful();

        $this->request($client, 'POST', \sprintf('/admin/api/content/pages/%s/builder/rollback', $pageId), ['revisionId' => $revisionId]);
        self::assertResponseIsSuccessful();
        self::assertSame('Старый SEO title страницы', $this->metaTitle($client));
    }

    public function testSeoAuditReportsTitleLengthAndDuplicates(): void
    {
        $client = $this->clientWithAdmin('meta-title-audit@example.test');
        $first = $this->createPage($client, 'Первая страница заборов', '/audit-first/');
        $second = $this->createPage($client, 'Вторая страница заборов', '/audit-second/');

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $first), ['metaTitle' => 'Общий SEO title для двух страниц']);
        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $second), ['metaTitle' => 'общий seo title для двух страниц']);
        $this->request($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $first));
        self::assertResponseIsSuccessful();

        $this->request($client, 'GET', \sprintf('/admin/api/seo/audit/pages/%s', $second));
        self::assertResponseIsSuccessful();
        self::assertContains('seo.title.duplicate', $this->issueCodes($client));

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $second), ['metaTitle' => str_repeat('а', 61)]);
        $this->request($client, 'GET', \sprintf('/admin/api/seo/audit/pages/%s', $second));
        $codes = $this->issueCodes($client);
        self::assertContains('seo.title.too_long', $codes);
        self::assertNotContains('seo.title.duplicate', $codes);

        $this->request($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $second), ['metaTitle' => 'Короткий']);
        $this->request($client, 'GET', \sprintf('/admin/api/seo/audit/pages/%s', $second));
        self::assertContains('seo.title.too_short', $this->issueCodes($client));
    }

    private function assertMetaTitleIsNull(KernelBrowser $client): void
    {
        self::assertNull($this->metaTitle($client));
    }

    private function metaTitle(KernelBrowser $client): ?string
    {
        $seo = $this->json($client)['seo'] ?? null;
        self::assertIsArray($seo);
        self::assertArrayHasKey('metaTitle', $seo);
        self::assertTrue($seo['metaTitle'] === null || \is_string($seo['metaTitle']));

        return $seo['metaTitle'];
    }

    private function clientWithAdmin(string $email): KernelBrowser
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $user = new AdminUser($email, 'hash', ['ROLE_ADMIN']);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();
        $client->loginUser($user);

        return $client;
    }

    private function createPage(KernelBrowser $client, string $title, string $path): string
    {
        $this->request($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => $title,
            'slug' => trim($path, '/'),
            'path' => $path,
            'h1' => $title,
        ]);
        self::assertResponseStatusCodeSame(201);
        $id = $this->json($client)['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /**
     * @return list<string>
     */
    private function issueCodes(KernelBrowser $client): array
    {
        $issues = $this->json($client)['issues'] ?? [];
        self::assertIsArray($issues);

        return array_values(array_map(
            static fn (mixed $issue): string => \is_array($issue) && \is_string($issue['code'] ?? null) ? $issue['code'] : '',
            $issues,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function json(KernelBrowser $client): array
    {
        $decoded = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            throw new LogicException('Response must be a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(KernelBrowser $client, string $method, string $uri, array $payload = []): void
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

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }
}

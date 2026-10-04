<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber;
use App\Module\Content\Application\Service\PageTemplateBlocks;
use App\Module\Content\Domain\Entity\PageTemplate;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PageTemplatesAndDuplicationTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testPageCreatedFromSavedTemplateGetsItsBlocks(): void
    {
        $client = $this->adminClient();

        $template = $this->saveTemplate($client, 'Забор из профнастила', 'page', [
            ['type' => 'hero.classic', 'content' => ['title' => 'Забор под ключ'], 'hint' => 'Заполните город'],
            ['type' => 'faq', 'content' => ['items' => [['question' => 'Сколько стоит?', 'answer' => 'От 1500 ₽/м.']]], 'enabled' => false],
        ]);
        self::assertStringStartsWith('custom_', $this->str($template, 'code'));
        self::assertSame('page', $template['kind']);
        self::assertSame('Заполните город', $this->listOf($template, 'blocksSchema')[0]['hint']);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'service',
            'title' => 'Заборы',
            'slug' => 'zabory',
            'path' => '/zabory/',
            'h1' => 'Заборы',
            'template' => $this->str($template, 'code'),
            'starterTemplate' => $this->str($template, 'code'),
        ]);
        self::assertResponseStatusCodeSame(201);
        $pageId = $this->stringFromResponse($client, 'id');

        $client->request('GET', \sprintf('/admin/api/content/pages/%s/builder', $pageId));
        self::assertResponseIsSuccessful();
        $blocks = $this->jsonList($client, 'blocks');
        self::assertCount(2, $blocks);
        self::assertSame('hero.classic', $blocks[0]['type']);
        self::assertTrue($blocks[0]['enabled']);
        self::assertSame('faq', $blocks[1]['type']);
        self::assertFalse($blocks[1]['enabled']);
    }

    public function testStarterTemplateMustExist(): void
    {
        $client = $this->adminClient();

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'service',
            'title' => 'Заборы',
            'slug' => 'zabory',
            'path' => '/zabory/',
            'h1' => 'Заборы',
            'starterTemplate' => 'missing_template',
        ]);

        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/admin/api/content/pages');
        self::assertSame([], $this->jsonList($client, 'pages'));
    }

    public function testInvalidTemplateBlocksAreRejected(): void
    {
        $client = $this->adminClient();

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/templates', [
            'name' => 'Пустой',
            'kind' => 'page',
            'pageType' => 'service',
            'blocks' => [],
        ]);
        self::assertResponseStatusCodeSame(422);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/templates', [
            'name' => 'Без заголовка',
            'kind' => 'page',
            'pageType' => 'service',
            'blocks' => [['type' => 'hero.classic', 'content' => ['title' => '']]],
        ]);
        self::assertResponseStatusCodeSame(422);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/templates', [
            'name' => 'Неизвестный вид',
            'kind' => 'widget',
            'pageType' => 'service',
            'blocks' => [['type' => 'rich-text', 'content' => ['html' => '<p>Текст</p>']]],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testSectionTemplatesAreListedSeparatelyAndCanBeRemoved(): void
    {
        $client = $this->adminClient();

        $section = $this->saveTemplate($client, 'Блок FAQ про гарантию', 'section', [
            ['type' => 'faq', 'content' => ['items' => [['question' => 'Какая гарантия?', 'answer' => '5 лет.']]]],
        ]);
        $page = $this->saveTemplate($client, 'Шаблон страницы', 'page', [
            ['type' => 'rich-text', 'content' => ['html' => '<p>Текст</p>']],
        ]);

        $client->request('GET', '/admin/api/content/templates?kind=section');
        self::assertSame([$this->str($section, 'code')], $this->codes($client));

        $client->request('GET', '/admin/api/content/templates');
        self::assertSame([$this->str($page, 'code')], $this->codes($client));

        $client->request('GET', '/admin/api/content/templates?kind=all');
        self::assertEqualsCanonicalizing([$this->str($section, 'code'), $this->str($page, 'code')], $this->codes($client));

        $this->jsonRequestWithCsrf($client, 'DELETE', '/admin/api/content/templates/'.$this->str($section, 'code'));
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/admin/api/content/templates?kind=section');
        self::assertSame([], $this->codes($client));
    }

    public function testSystemTemplateCannotBeRemoved(): void
    {
        $client = $this->adminClient();
        $entityManager = $this->entityManager();
        $entityManager->persist(new PageTemplate(
            'system_sample',
            'Системный',
            PageType::TextPage,
            [],
            system: true,
        ));
        $entityManager->flush();

        $this->jsonRequestWithCsrf($client, 'DELETE', '/admin/api/content/templates/system_sample');

        self::assertResponseStatusCodeSame(422);
        $client->request('GET', '/admin/api/content/templates');
        self::assertSame(['system_sample'], $this->codes($client));
    }

    public function testDuplicatePageCopiesBlocksAndSeoWithoutPathAndCanonical(): void
    {
        $client = $this->adminClient();
        $sourceId = $this->createPage($client, 'zabor', 'Забор');

        $this->jsonRequestWithCsrf($client, 'PUT', \sprintf('/admin/api/content/pages/%s/seo', $sourceId), [
            'metaTitle' => 'Купить забор',
            'metaDescription' => 'Забор под ключ.',
            'canonicalUrl' => 'https://zaborprofil.test/zabor/',
            'ogTitle' => 'Забор OG',
        ]);
        self::assertResponseIsSuccessful();

        $this->jsonRequestWithCsrf($client, 'PUT', \sprintf('/admin/api/content/pages/%s/builder', $sourceId), [
            'blocks' => [
                ['type' => 'hero.classic', 'enabled' => true, 'content' => ['title' => 'Забор'], 'settings' => []],
                ['type' => 'rich-text', 'enabled' => false, 'content' => ['html' => '<p>Скрытый текст</p>'], 'settings' => []],
            ],
        ]);
        self::assertResponseIsSuccessful();

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/duplicate', $sourceId));
        self::assertResponseStatusCodeSame(201);
        $copy = $this->json($client);

        self::assertNotSame($sourceId, $copy['id']);
        self::assertSame('/zabor-copy/', $copy['path']);
        self::assertSame('zabor-copy', $copy['slug']);
        self::assertSame('Забор (копия)', $copy['title']);
        self::assertSame('draft', $copy['status']);
        $seo = $this->child($copy, 'seo');
        self::assertSame('Купить забор', $seo['metaTitle']);
        self::assertSame('Забор под ключ.', $seo['metaDescription']);
        self::assertSame('Забор OG', $seo['ogTitle']);
        self::assertNull($seo['canonicalUrl']);

        $client->request('GET', \sprintf('/admin/api/content/pages/%s/builder', $this->str($copy, 'id')));
        $blocks = $this->jsonList($client, 'blocks');
        self::assertSame(['hero.classic', 'rich-text'], array_column($blocks, 'type'));
        self::assertSame([true, false], array_column($blocks, 'enabled'));

        $client->request('GET', \sprintf('/admin/api/content/pages/%s/builder', $sourceId));
        $sourceBlocks = $this->jsonList($client, 'blocks');
        self::assertCount(2, $sourceBlocks);
        self::assertNotSame($sourceBlocks[0]['id'], $blocks[0]['id']);

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/duplicate', $sourceId));
        self::assertResponseStatusCodeSame(201);
        self::assertSame('/zabor-copy-2/', $this->json($client)['path']);
    }

    public function testDuplicateCanUseCustomTitleAndPathButRejectsTakenPath(): void
    {
        $client = $this->adminClient();
        $sourceId = $this->createPage($client, 'zabor', 'Забор');
        $this->createPage($client, 'taken', 'Занято');

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/duplicate', $sourceId), [
            'title' => 'Забор в Туле',
            'slug' => 'zabor-tula',
            'path' => '/tula/zabor/',
        ]);
        self::assertResponseStatusCodeSame(201);
        $copy = $this->json($client);
        self::assertSame('/tula/zabor/', $copy['path']);
        self::assertSame('zabor-tula', $copy['slug']);
        self::assertSame('Забор в Туле', $copy['title']);

        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/duplicate', $sourceId), ['path' => '/taken/']);
        self::assertResponseStatusCodeSame(422);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages/01JZZZZZZZZZZZZZZZZZZZZZZZ/duplicate');
        self::assertResponseStatusCodeSame(404);
    }

    public function testBulkStatusChangeReportsResultPerPage(): void
    {
        $client = $this->adminClient();
        $first = $this->createPage($client, 'one', 'Один');
        $second = $this->createPage($client, 'two', 'Два');

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages/bulk', [
            'ids' => [$first, $second],
            'action' => 'status',
            'status' => 'review',
        ]);
        self::assertResponseIsSuccessful();
        $result = $this->json($client);
        self::assertSame(2, $result['succeeded']);
        self::assertSame(0, $result['failed']);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages/bulk', [
            'ids' => [$first, '01JZZZZZZZZZZZZZZZZZZZZZZZ'],
            'action' => 'status',
            'status' => 'draft',
        ]);
        self::assertResponseIsSuccessful();
        $result = $this->json($client);
        self::assertSame(1, $result['succeeded']);
        self::assertSame(1, $result['failed']);
        self::assertFalse($this->listOf($result, 'results')[1]['ok']);

        $client->request('GET', '/admin/api/content/pages');
        $statuses = array_column($this->jsonList($client, 'pages'), 'status', 'id');
        self::assertSame('draft', $statuses[$first]);
        self::assertSame('review', $statuses[$second]);
    }

    public function testBulkRejectsPublishingUnknownActionAndEmptySelection(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'one', 'Один');

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages/bulk', ['ids' => [$pageId], 'action' => 'status', 'status' => 'published']);
        self::assertResponseStatusCodeSame(422);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages/bulk', ['ids' => [$pageId], 'action' => 'explode']);
        self::assertResponseStatusCodeSame(422);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages/bulk', ['ids' => [], 'action' => 'indexable', 'indexable' => false]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testBulkNoindexAffectsAlreadyPublishedPage(): void
    {
        $client = $this->adminClient();
        $pageId = $this->createPage($client, 'public-page', 'Публичная');
        $this->jsonRequestWithCsrf($client, 'PUT', \sprintf('/admin/api/content/pages/%s/builder', $pageId), [
            'blocks' => [['type' => 'hero.classic', 'enabled' => true, 'content' => ['title' => 'Публичная'], 'settings' => []]],
        ]);
        self::assertResponseIsSuccessful();
        $this->jsonRequestWithCsrf($client, 'POST', \sprintf('/admin/api/content/pages/%s/publish', $pageId));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/public-page/');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('noindex', (string) $client->getResponse()->getContent());

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages/bulk', [
            'ids' => [$pageId],
            'action' => 'indexable',
            'indexable' => false,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json($client)['succeeded']);

        $client->request('GET', '/public-page/');
        self::assertResponseIsSuccessful();
        self::assertMatchesRegularExpression('/<meta name="robots" content="noindex/', (string) $client->getResponse()->getContent());

        $client->request('GET', \sprintf('/admin/api/content/pages/%s', $pageId));
        self::assertFalse($this->json($client)['isIndexable']);

        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages/bulk', [
            'ids' => [$pageId],
            'action' => 'indexable',
            'indexable' => true,
        ]);
        $client->request('GET', '/public-page/');
        self::assertStringNotContainsString('noindex', (string) $client->getResponse()->getContent());
    }

    public function testMigrationSeededTemplatesContainValidBlocksWithHints(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $blocks = self::getContainer()->get(PageTemplateBlocks::class);
        self::assertInstanceOf(PageTemplateBlocks::class, $blocks);

        $file = \dirname(__DIR__, 3).'/migrations/Version20261014153700.php';
        require_once $file;
        $migrationClass = 'DoctrineMigrations\\'.pathinfo($file, \PATHINFO_FILENAME);
        if (!class_exists($migrationClass)) {
            throw new LogicException('Migration class is not loadable.');
        }
        $migration = (new ReflectionClass($migrationClass))->newInstance($connection, new NullLogger());

        $templates = [];
        foreach (['systemTemplates', 'newTemplates'] as $method) {
            $found = (new ReflectionMethod($migration, $method))->invoke($migration);
            $templates = [...$templates, ...$this->stringKeyed($found)];
        }

        $codes = array_keys($templates);
        foreach (['fence_profnastil', 'fence_jalousie', 'gates_wickets', 'prices', 'portfolio_index', 'contacts'] as $expected) {
            self::assertContains($expected, $codes);
        }

        foreach ($templates as $code => $template) {
            $templateBlocks = $this->listOf($this->stringKeyed($template), 'blocks');
            $encoded = json_decode(json_encode($templateBlocks, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($encoded);
            $normalized = $blocks->normalize($encoded);
            self::assertCount(\count($templateBlocks), $normalized, (string) $code);
            foreach ($normalized as $block) {
                self::assertArrayHasKey('hint', $block, $code.': '.$this->str($block, 'type'));
            }
        }
    }

    private function adminClient(): KernelBrowser
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $entityManager = $this->entityManager();
        $user = new AdminUser('admin@example.test', 'hash', ['ROLE_ADMIN']);
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        return $client;
    }

    private function createPage(KernelBrowser $client, string $slug, string $title): string
    {
        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/pages', [
            'type' => 'landing',
            'title' => $title,
            'slug' => $slug,
            'path' => \sprintf('/%s/', $slug),
            'h1' => $title,
        ]);
        self::assertResponseStatusCodeSame(201);

        return $this->stringFromResponse($client, 'id');
    }

    /**
     * @param list<array<string, mixed>> $blocks
     *
     * @return array<string, mixed>
     */
    private function saveTemplate(KernelBrowser $client, string $name, string $kind, array $blocks): array
    {
        $this->jsonRequestWithCsrf($client, 'POST', '/admin/api/content/templates', [
            'name' => $name,
            'description' => 'Описание',
            'kind' => $kind,
            'pageType' => 'service',
            'blocks' => $blocks,
        ]);
        self::assertResponseStatusCodeSame(201);

        return $this->json($client);
    }

    /**
     * @return list<string>
     */
    private function codes(KernelBrowser $client): array
    {
        return array_map(fn (array $template): string => $this->str($template, 'code'), $this->jsonList($client, 'templates'));
    }

    /**
     * @return array<string, mixed>
     */
    private function json(KernelBrowser $client): array
    {
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $this->stringKeyed($payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonList(KernelBrowser $client, string $key): array
    {
        return $this->listOf($this->json($client), $key);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function listOf(array $data, string $key): array
    {
        $list = $data[$key] ?? null;
        if (!\is_array($list)) {
            throw new LogicException(\sprintf('Field "%s" must be a list.', $key));
        }

        return array_map($this->stringKeyed(...), array_values($list));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function child(array $data, string $key): array
    {
        return $this->stringKeyed($data[$key] ?? null);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function str(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value)) {
            throw new LogicException(\sprintf('Field "%s" must be a string.', $key));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function stringKeyed(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new LogicException('Value must be an object.');
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }

    private function stringFromResponse(KernelBrowser $client, string $key): string
    {
        return $this->str($this->json($client), $key);
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

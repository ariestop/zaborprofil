<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicPageHttpCacheTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
    }

    public function testPublishedPageIsPubliclyCacheableWithEtag(): void
    {
        $client = self::createClient();
        $this->createPublishedPage('/zabory/', 'Заборы');

        $client->request('GET', '/zabory/');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('s-maxage=300', $cacheControl);
        self::assertNotNull($response->headers->get('ETag'));
        self::assertFalse($response->headers->has('Set-Cookie'));
    }

    public function testConditionalRequestReturnsNotModified(): void
    {
        $client = self::createClient();
        $this->createPublishedPage('/zabory/', 'Заборы');

        $client->request('GET', '/zabory/');
        $etag = (string) $client->getResponse()->headers->get('ETag');

        $client->request('GET', '/zabory/', server: ['HTTP_IF_NONE_MATCH' => $etag]);

        self::assertResponseStatusCodeSame(304);
        self::assertSame('', $client->getResponse()->getContent());
    }

    public function testLeadFormDoesNotEmbedRequestSpecificValues(): void
    {
        $client = self::createClient();
        $this->createPublishedPage('/zabory/', 'Заборы');

        $crawler = $client->request('GET', '/zabory/?utm_source=test');

        self::assertResponseIsSuccessful();
        self::assertSame('', $crawler->filter('input[name="formLoadedAt"]')->attr('value'));
        self::assertSame('', $crawler->filter('input[name="pageUrl"]')->attr('value'));
        self::assertStringNotContainsString('utm_source', (string) $client->getResponse()->getContent());
    }

    public function testMissingPageIsNotCachedPublicly(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $client->request('GET', '/missing/');

        self::assertResponseStatusCodeSame(404);
        $cacheControl = (string) $client->getResponse()->headers->get('Cache-Control');
        self::assertStringNotContainsString('public', $cacheControl);
        self::assertFalse($client->getResponse()->headers->has('ETag'));
    }

    private function createPublishedPage(string $path, string $title): void
    {
        SchemaTestHelper::recreateSchema($this->entityManager());

        $page = new Page(PageType::Landing, $title, trim($path, '/'), $path, $title);
        $page->publish();
        $this->pageRepository()->save($page);
    }

    private function pageRepository(): PageRepositoryInterface
    {
        $repository = self::getContainer()->get(PageRepositoryInterface::class);

        if (!$repository instanceof PageRepositoryInterface) {
            throw new LogicException('PageRepositoryInterface service is not available.');
        }

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('EntityManagerInterface service is not available.');
        }

        return $entityManager;
    }
}

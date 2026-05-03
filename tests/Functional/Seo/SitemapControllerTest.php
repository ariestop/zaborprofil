<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SitemapControllerTest extends WebTestCase
{
    public function testSitemapListsOnlyPublishedIndexablePages(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $repository = $this->pageRepository();

        $published = new Page(PageType::Landing, 'Заборы', 'fences', '/fences/', 'Заборы');
        $published->publish();
        $repository->save($published);

        $draft = new Page(PageType::Landing, 'Черновик', 'draft', '/draft/', 'Черновик');
        $repository->save($draft);

        $hidden = new Page(PageType::Landing, 'Скрыт', 'hidden', '/hidden/', 'Скрыт', 'default', 0, false);
        $hidden->publish();
        $repository->save($hidden);

        $client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertSame('application/xml; charset=UTF-8', $client->getResponse()->headers->get('Content-Type'));

        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<loc>https://zaborprofil.test/fences/</loc>', $body);
        self::assertStringNotContainsString('/draft/', $body);
        self::assertStringNotContainsString('/hidden/', $body);
    }

    public function testSitemapUsesIndexWhenPublishedPagesExceedChunkSize(): void
    {
        $client = self::createClient();
        SchemaTestHelper::recreateSchema($this->entityManager());

        $repository = $this->pageRepository();
        foreach ([1, 2, 3] as $number) {
            $page = new Page(PageType::Landing, 'Страница '.$number, 'chunk-'.$number, '/chunk-'.$number.'/', 'Страница '.$number);
            $page->publish();
            $repository->save($page);
        }

        $client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();

        $index = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $index);
        self::assertStringContainsString('<loc>https://zaborprofil.test/sitemap-pages-1.xml</loc>', $index);
        self::assertStringContainsString('<loc>https://zaborprofil.test/sitemap-pages-2.xml</loc>', $index);

        $client->request('GET', '/sitemap-pages-1.xml');
        self::assertResponseIsSuccessful();
        $firstChunk = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('/chunk-1/', $firstChunk);
        self::assertStringContainsString('/chunk-2/', $firstChunk);
        self::assertStringNotContainsString('/chunk-3/', $firstChunk);

        $client->request('GET', '/sitemap-pages-2.xml');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/chunk-3/', (string) $client->getResponse()->getContent());
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager service is not available.');
        }

        return $entityManager;
    }

    private function pageRepository(): PageRepositoryInterface
    {
        $repository = self::getContainer()->get(PageRepositoryInterface::class);

        if (!$repository instanceof PageRepositoryInterface) {
            throw new LogicException('Page repository service is not available.');
        }

        return $repository;
    }
}

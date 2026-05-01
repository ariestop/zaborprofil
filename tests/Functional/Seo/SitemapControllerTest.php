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

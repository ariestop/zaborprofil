<?php

declare(strict_types=1);

namespace App\Tests\Integration\Content;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineContentRepositoryTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = $this->entityManager();
        $connection = $entityManager->getConnection();

        $connection->executeStatement('DROP TABLE IF EXISTS content_page_blocks');
        $connection->executeStatement('DROP TABLE IF EXISTS content_pages');
        $connection->executeStatement('CREATE TABLE content_pages (id CHAR(26) NOT NULL PRIMARY KEY, parent_id CHAR(26) DEFAULT NULL, type VARCHAR(32) NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(180) NOT NULL, path VARCHAR(512) NOT NULL, h1 VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, template VARCHAR(120) NOT NULL, sort_order INTEGER NOT NULL, indexable BOOLEAN NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL)');
        $connection->executeStatement('CREATE UNIQUE INDEX uniq_content_pages_path ON content_pages (path)');
        $connection->executeStatement('CREATE TABLE content_page_blocks (id CHAR(26) NOT NULL PRIMARY KEY, page_id CHAR(26) NOT NULL, type VARCHAR(64) NOT NULL, name VARCHAR(180) NOT NULL, position INTEGER NOT NULL, is_enabled BOOLEAN NOT NULL, content CLOB NOT NULL, settings CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
    }

    public function testPublishedPageCanBeLoadedByPathWithBlocks(): void
    {
        $pages = $this->pageRepository();
        $blocks = $this->pageBlockRepository();

        $page = new Page(PageType::Landing, 'Заборы', 'zabory', '/zabory/', 'Заборы');
        $page->publish();
        $pages->save($page);

        $block = new PageBlock($page, BlockType::Hero, 'Hero', 0, ['title' => 'Hero'], ['wide' => true]);
        $blocks->save($block);

        $loadedPage = $pages->findPublishedByPath('/zabory/');

        self::assertNotNull($loadedPage);
        self::assertSame('Заборы', $loadedPage->title());
        self::assertTrue($pages->existsByPath('/zabory/'));
        self::assertCount(1, $blocks->findByPage($page->id()));
        self::assertSame(['title' => 'Hero'], $blocks->findByPage($page->id())[0]->content());
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

    private function pageBlockRepository(): PageBlockRepositoryInterface
    {
        $repository = self::getContainer()->get(PageBlockRepositoryInterface::class);

        if (!$repository instanceof PageBlockRepositoryInterface) {
            throw new LogicException('Page block repository service is not available.');
        }

        return $repository;
    }
}

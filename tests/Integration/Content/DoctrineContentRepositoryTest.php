<?php

declare(strict_types=1);

namespace App\Tests\Integration\Content;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineContentRepositoryTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        SchemaTestHelper::recreateSchema($this->entityManager());
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
        self::assertCount(1, $blocks->findByPage((string) $page->id()));
        self::assertSame(['title' => 'Hero'], $blocks->findByPage((string) $page->id())[0]->content());
    }

    public function testExistsByPathIgnoresSoftDeletedPagesSoEditorCanReusePath(): void
    {
        $pages = $this->pageRepository();

        $page = new Page(PageType::Landing, 'Старый', 'old-page', '/recyclable/', 'Старый');
        $pages->save($page);
        self::assertTrue($pages->existsByPath('/recyclable/'));

        $page->delete();
        $pages->save($page);

        self::assertFalse(
            $pages->existsByPath('/recyclable/'),
            'A soft-deleted page must not block reuse of its path by a fresh page.',
        );
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

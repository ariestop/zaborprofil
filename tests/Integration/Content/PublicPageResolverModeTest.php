<?php

declare(strict_types=1);

namespace App\Tests\Integration\Content;

use App\Module\Content\Application\Service\PublicPageResolver;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Entity\PagePublication;
use App\Module\Content\Domain\Entity\PageRevision;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRevisionRepositoryInterface;
use App\Module\Settings\Application\Service\SettingsService;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PublicPageResolverModeTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        SchemaTestHelper::recreateSchema($this->entityManager());
    }

    public function testResolverSwitchesBetweenSnapshotAndLiveBlocksBySetting(): void
    {
        $page = new Page(
            PageType::Landing,
            'Render mode page',
            'render-mode',
            '/render-mode/',
            'Render mode page',
        );
        $page->publish();
        $this->pages()->save($page);

        $liveBlock = new PageBlock(
            $page,
            BlockType::HeroClassic,
            'Live hero',
            0,
            ['title' => 'Live block title'],
            [],
            true,
        );
        $this->blocks()->save($liveBlock);

        $revision = new PageRevision(
            $page,
            1,
            $page->title(),
            $page->h1(),
            $page->slug(),
            $page->path(),
            $page->type()->value,
            $page->template(),
            $page->status()->value,
            ['isIndexable' => true],
            [[
                'id' => 'snapshot-hero-1',
                'type' => 'hero.classic',
                'name' => 'Snapshot hero',
                'position' => 0,
                'content' => ['title' => 'Snapshot block title'],
                'settings' => [],
                'isEnabled' => true,
            ]],
            [],
        );
        $this->revisions()->save($revision);

        $publication = new PagePublication($page);
        $publication->publish($revision);
        $this->publications()->save($publication);

        $snapshotView = $this->resolver()->resolve('/render-mode/');
        self::assertNotNull($snapshotView);
        self::assertSame('Snapshot block title', $snapshotView->blocks[0]->content['title'] ?? null);

        $this->settings()->set('content', 'public_page_blocks_source', 'live');

        $liveView = $this->resolver()->resolve('/render-mode/');
        self::assertNotNull($liveView);
        self::assertSame('Live block title', $liveView->blocks[0]->content['title'] ?? null);
    }

    private function resolver(): PublicPageResolver
    {
        $resolver = self::getContainer()->get(PublicPageResolver::class);
        if (!$resolver instanceof PublicPageResolver) {
            throw new LogicException('PublicPageResolver service is not available.');
        }

        return $resolver;
    }

    private function settings(): SettingsService
    {
        $service = self::getContainer()->get(SettingsService::class);
        if (!$service instanceof SettingsService) {
            throw new LogicException('SettingsService is not available.');
        }

        return $service;
    }

    private function pages(): PageRepositoryInterface
    {
        $repository = self::getContainer()->get(PageRepositoryInterface::class);
        if (!$repository instanceof PageRepositoryInterface) {
            throw new LogicException('PageRepositoryInterface is not available.');
        }

        return $repository;
    }

    private function blocks(): PageBlockRepositoryInterface
    {
        $repository = self::getContainer()->get(PageBlockRepositoryInterface::class);
        if (!$repository instanceof PageBlockRepositoryInterface) {
            throw new LogicException('PageBlockRepositoryInterface is not available.');
        }

        return $repository;
    }

    private function revisions(): PageRevisionRepositoryInterface
    {
        $repository = self::getContainer()->get(PageRevisionRepositoryInterface::class);
        if (!$repository instanceof PageRevisionRepositoryInterface) {
            throw new LogicException('PageRevisionRepositoryInterface is not available.');
        }

        return $repository;
    }

    private function publications(): PagePublicationRepositoryInterface
    {
        $repository = self::getContainer()->get(PagePublicationRepositoryInterface::class);
        if (!$repository instanceof PagePublicationRepositoryInterface) {
            throw new LogicException('PagePublicationRepositoryInterface is not available.');
        }

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Entity manager is not available.');
        }

        return $entityManager;
    }
}

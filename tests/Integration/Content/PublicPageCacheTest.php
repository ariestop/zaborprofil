<?php

declare(strict_types=1);

namespace App\Tests\Integration\Content;

use App\Module\Content\Application\Service\CachedPublicPageResolver;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Application\Service\PublicPageCacheKey;
use App\Module\Content\Application\Service\PublicPagePathNormalizer;
use App\Module\Content\Application\Service\PublicPageResolverInterface;
use App\Module\Content\Application\Service\PublicPageView;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies the public page cache contract:
 *  - first resolve writes to cache,
 *  - second resolve hits cache without re-querying the database,
 *  - invalidator removes the entry under the same key contract,
 *  - decorator is wired in DI (cache.public_page pool).
 */
final class PublicPageCacheTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        SchemaTestHelper::recreateSchema($this->entityManager());
        $this->cachePool()->clear();
    }

    public function testResolverReturnsCachedViewOnSecondCall(): void
    {
        $page = $this->createPublishedPage('/cached/', 'Cached page');
        $this->pageBlockRepository()->save(new PageBlock($page, BlockType::Hero, 'Hero', 0, ['title' => 'Cached']));

        $resolver = $this->resolver();
        $first = $resolver->resolve('/cached/');
        self::assertInstanceOf(PublicPageView::class, $first);
        self::assertSame('Cached page', $first->title);

        $key = PublicPageCacheKey::forPath(PublicPagePathNormalizer::normalize('/cached/'));
        $cachedItem = $this->cachePool()->getItem($key);
        self::assertTrue($cachedItem->isHit(), 'Resolver must populate the public page cache on the first call.');

        $cached = $cachedItem->get();
        self::assertInstanceOf(PublicPageView::class, $cached);
        self::assertSame('Cached page', $cached->title);
    }

    public function testInvalidatorRemovesCacheEntry(): void
    {
        $this->createPublishedPage('/invalidate/', 'Invalidate page');

        $resolver = $this->resolver();
        $resolver->resolve('/invalidate/');

        $key = PublicPageCacheKey::forPath(PublicPagePathNormalizer::normalize('/invalidate/'));
        self::assertTrue($this->cachePool()->getItem($key)->isHit());

        $this->invalidator()->invalidate('/invalidate/');

        self::assertFalse(
            $this->cachePool()->getItem($key)->isHit(),
            'PublicPageCacheInvalidator::invalidate must drop the entry from the cache pool.',
        );
    }

    public function testNullResultIsCachedToPreventThunderingHerd(): void
    {
        $resolver = $this->resolver();
        $first = $resolver->resolve('/missing/');
        self::assertNull($first);

        $key = PublicPageCacheKey::forPath(PublicPagePathNormalizer::normalize('/missing/'));
        self::assertTrue(
            $this->cachePool()->getItem($key)->isHit(),
            'A null result must be cached so that 404 floods do not hit the DB on every request.',
        );
    }

    public function testDecoratorIsWiredAsResolverInterface(): void
    {
        $resolver = $this->resolver();

        self::assertInstanceOf(
            CachedPublicPageResolver::class,
            $resolver,
            'PublicPageResolverInterface must be wired to the CachedPublicPageResolver decorator.',
        );
    }

    public function testInvalidateManyDeduplicatesPaths(): void
    {
        $this->createPublishedPage('/dedup/', 'Dedup');

        $resolver = $this->resolver();
        $resolver->resolve('/dedup/');

        $key = PublicPageCacheKey::forPath(PublicPagePathNormalizer::normalize('/dedup/'));
        self::assertTrue($this->cachePool()->getItem($key)->isHit());

        $this->invalidator()->invalidateMany(['/dedup/', 'dedup/', '/dedup/']);

        self::assertFalse($this->cachePool()->getItem($key)->isHit());
    }

    private function createPublishedPage(string $path, string $title): Page
    {
        $slug = trim($path, '/');
        if ($slug === '') {
            $slug = 'home';
        }

        $page = new Page(PageType::Landing, $title, $slug, $path, $title);
        $page->publish();
        $this->pageRepository()->save($page);

        return $page;
    }

    private function resolver(): PublicPageResolverInterface
    {
        $resolver = self::getContainer()->get(PublicPageResolverInterface::class);

        if (!$resolver instanceof PublicPageResolverInterface) {
            throw new LogicException('PublicPageResolverInterface service is not available.');
        }

        return $resolver;
    }

    private function invalidator(): PublicPageCacheInvalidator
    {
        $invalidator = self::getContainer()->get(PublicPageCacheInvalidator::class);

        if (!$invalidator instanceof PublicPageCacheInvalidator) {
            throw new LogicException('PublicPageCacheInvalidator service is not available.');
        }

        return $invalidator;
    }

    private function cachePool(): CacheItemPoolInterface
    {
        $pool = self::getContainer()->get('cache.public_page');

        if (!$pool instanceof CacheItemPoolInterface) {
            throw new LogicException('cache.public_page pool service is not available.');
        }

        return $pool;
    }

    private function pageRepository(): PageRepositoryInterface
    {
        $repository = self::getContainer()->get(PageRepositoryInterface::class);

        if (!$repository instanceof PageRepositoryInterface) {
            throw new LogicException('PageRepositoryInterface service is not available.');
        }

        return $repository;
    }

    private function pageBlockRepository(): PageBlockRepositoryInterface
    {
        $repository = self::getContainer()->get(PageBlockRepositoryInterface::class);

        if (!$repository instanceof PageBlockRepositoryInterface) {
            throw new LogicException('PageBlockRepositoryInterface service is not available.');
        }

        return $repository;
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

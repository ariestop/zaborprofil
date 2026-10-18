<?php

declare(strict_types=1);

namespace App\Module\Menu\Application\Service;

use App\Module\Content\Application\Service\PublicHttpCachePurgerInterface;
use App\Module\Menu\Domain\Entity\MenuItem;
use App\Module\Menu\Domain\Repository\MenuItemRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class MenuProvider
{
    public function __construct(
        private MenuItemRepositoryInterface $items,
        #[Autowire(service: 'cache.menu')]
        private CacheInterface $cache,
        private PublicHttpCachePurgerInterface $httpCachePurger,
    ) {
    }

    /**
     * Активные пункты позиции деревом: разделы верхнего уровня, у каждого — `children` с активными подпунктами.
     * Подпункт выключенного раздела на сайт не попадает.
     *
     * @return list<array<string, mixed>>
     */
    public function items(string $position): array
    {
        return $this->cache->get($this->cacheKey($position), function (ItemInterface $item) use ($position): array {
            $item->expiresAfter(3600);

            return self::tree($this->items->findActiveByPosition($position));
        });
    }

    public function invalidate(string $position): void
    {
        $this->cache->delete($this->cacheKey($position));
        $this->httpCachePurger->purgeAll();
    }

    /**
     * @param list<MenuItem> $items отсортированы по sortOrder
     *
     * @return list<array<string, mixed>>
     */
    private static function tree(array $items): array
    {
        $children = [];
        foreach ($items as $menuItem) {
            $parentId = $menuItem->parentId();
            if ($parentId !== null) {
                $children[(string) $parentId][] = $menuItem->toArray();
            }
        }

        $tree = [];
        foreach ($items as $menuItem) {
            if ($menuItem->parentId() === null) {
                $tree[] = [...$menuItem->toArray(), 'children' => $children[(string) $menuItem->id()] ?? []];
            }
        }

        return $tree;
    }

    private function cacheKey(string $position): string
    {
        return 'menu.tree.'.hash('xxh128', $position);
    }
}

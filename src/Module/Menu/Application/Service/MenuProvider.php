<?php

declare(strict_types=1);

namespace App\Module\Menu\Application\Service;

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
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(string $position): array
    {
        return $this->cache->get($this->cacheKey($position), function (ItemInterface $item) use ($position): array {
            $item->expiresAfter(3600);

            return array_map(static fn ($menuItem): array => $menuItem->toArray(), $this->items->findActiveByPosition($position));
        });
    }

    public function invalidate(string $position): void
    {
        $this->cache->delete($this->cacheKey($position));
    }

    private function cacheKey(string $position): string
    {
        return 'menu.position.'.hash('xxh128', $position);
    }
}

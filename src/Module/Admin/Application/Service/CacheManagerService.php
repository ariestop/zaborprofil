<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use DateTimeImmutable;
use Symfony\Component\Cache\Adapter\AdapterInterface;

final readonly class CacheManagerService
{
    public function __construct(
        private AdapterInterface $appCache,
        private WhitelistCommandRunner $commandRunner,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $namespace = method_exists($this->appCache, 'getNamespace') ? $this->appCache->getNamespace() : '';

        return [
            'adapter' => $this->appCache::class,
            'namespace' => \is_string($namespace) ? $namespace : '',
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clear(): array
    {
        return $this->commandRunner->run('cache.clear.app');
    }
}

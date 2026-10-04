<?php

declare(strict_types=1);

namespace App\Tests\Support\Content;

use App\Module\Content\Application\Service\PublicHttpCachePurgerInterface;

final class RecordingHttpCachePurger implements PublicHttpCachePurgerInterface
{
    /** @var list<string> */
    public array $paths = [];

    public int $purgeAllCalls = 0;

    public function purgePath(string $path): void
    {
        $this->paths[] = $path;
    }

    public function purgeAll(): void
    {
        ++$this->purgeAllCalls;
    }
}

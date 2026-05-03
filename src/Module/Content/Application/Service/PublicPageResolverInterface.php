<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

interface PublicPageResolverInterface
{
    public function resolve(string $path): ?PublicPageView;
}

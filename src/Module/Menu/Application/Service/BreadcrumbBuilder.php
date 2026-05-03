<?php

declare(strict_types=1);

namespace App\Module\Menu\Application\Service;

use App\Module\Content\Application\Service\PublicPageView;

final readonly class BreadcrumbBuilder
{
    /**
     * @return list<BreadcrumbItem>
     */
    public function forPage(PublicPageView $page): array
    {
        $breadcrumbs = [
            new BreadcrumbItem('Главная', '/'),
        ];

        $segments = array_values(array_filter(explode('/', trim($page->path, '/')), static fn (string $segment): bool => $segment !== ''));
        $path = '';

        foreach ($segments as $index => $segment) {
            $path .= '/'.$segment;
            $current = $index === \count($segments) - 1;
            $breadcrumbs[] = new BreadcrumbItem(
                $current ? $page->h1 : $this->labelFromSegment($segment),
                $path.'/',
                $current,
            );
        }

        if (\count($breadcrumbs) === 1) {
            return [new BreadcrumbItem('Главная', '/', true)];
        }

        return $breadcrumbs;
    }

    private function labelFromSegment(string $segment): string
    {
        $label = str_replace(['-', '_'], ' ', $segment);

        return mb_convert_case($label, \MB_CASE_TITLE, 'UTF-8');
    }
}

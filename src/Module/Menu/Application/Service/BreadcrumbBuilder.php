<?php

declare(strict_types=1);

namespace App\Module\Menu\Application\Service;

use App\Module\Content\Application\Service\PublicPageView;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;

final readonly class BreadcrumbBuilder
{
    /**
     * @param PageRepositoryInterface|null $pages если передан, промежуточные «крошки» строятся только по существующим
     *                                            опубликованным страницам (без ссылок на несуществующие разделы)
     */
    public function __construct(private ?PageRepositoryInterface $pages = null)
    {
    }

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
            $label = $current ? $page->h1 : $this->labelFromSegment($segment);

            if (!$current && $this->pages instanceof PageRepositoryInterface) {
                $parent = $this->pages->findPublishedByPath($path.'/');
                if ($parent === null) {
                    continue;
                }
                $label = $parent->h1();
            }

            $breadcrumbs[] = new BreadcrumbItem($label, $path.'/', $current);
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

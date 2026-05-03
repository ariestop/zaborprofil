<?php

declare(strict_types=1);

namespace App\Module\Menu\UI\Twig;

use App\Module\Content\Application\Service\PublicPageView;
use App\Module\Menu\Application\Service\BreadcrumbBuilder;
use App\Module\Menu\Application\Service\MenuProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly MenuProvider $menus,
        private readonly BreadcrumbBuilder $breadcrumbs,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('menu_items', $this->menus->items(...)),
            new TwigFunction('breadcrumbs_for_page', $this->breadcrumbsForPage(...)),
        ];
    }

    /**
     * @return list<array{label: string, path: string, current: bool}>
     */
    public function breadcrumbsForPage(PublicPageView $page): array
    {
        return array_map(static fn ($item): array => $item->toArray(), $this->breadcrumbs->forPage($page));
    }
}

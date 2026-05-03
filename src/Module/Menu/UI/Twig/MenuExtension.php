<?php

declare(strict_types=1);

namespace App\Module\Menu\UI\Twig;

use App\Module\Menu\Application\Service\MenuProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MenuExtension extends AbstractExtension
{
    public function __construct(private readonly MenuProvider $menus)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('menu_items', $this->menus->items(...)),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Web;

use App\Module\Content\Application\Service\PageBlockView;
use Twig\Environment;
use Twig\Error\Error;

final readonly class TwigBlockRenderer
{
    public function __construct(private Environment $twig)
    {
    }

    public function render(PageBlockView $block): string
    {
        $template = \sprintf('public/blocks/%s.html.twig', $block->type);

        try {
            return $this->twig->render($template, ['block' => $block]);
        } catch (Error) {
            return $this->twig->render('public/blocks/default.html.twig', ['block' => $block]);
        }
    }
}

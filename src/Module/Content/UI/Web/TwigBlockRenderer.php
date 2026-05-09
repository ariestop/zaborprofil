<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Web;

use App\Module\Content\Application\Service\PageBlockView;
use Twig\Environment;
use Twig\Error\Error;

final readonly class TwigBlockRenderer
{
    /**
     * @var array<string, string>
     */
    private const TEMPLATE_ALIASES = [
        'hero.classic' => 'hero',
        'rich-text' => 'text',
        'features' => 'feature_grid',
        'cta' => 'cta_form',
        'contact-form' => 'contacts',
        'price-table' => 'table',
        'portfolio' => 'gallery',
    ];

    public function __construct(private Environment $twig)
    {
    }

    public function render(PageBlockView $block): string
    {
        $templateType = self::TEMPLATE_ALIASES[$block->type] ?? $block->type;
        $template = \sprintf('public/blocks/%s.html.twig', $templateType);

        try {
            return $this->twig->render($template, ['block' => $block]);
        } catch (Error) {
            return $this->twig->render('public/blocks/default.html.twig', ['block' => $block]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Web;

use App\Module\Content\Application\Service\PageBlockView;
use Twig\Environment;
use Twig\Error\Error;

final readonly class TwigBlockRenderer
{
    /**
     * Типы блоков первого экрана: если страница начинается с такого блока, заголовок H1 выводится внутри него.
     */
    private const array HEADING_BLOCK_TYPES = ['hero.classic', 'hero', 'hero.minimal'];

    public function __construct(private Environment $twig)
    {
    }

    /**
     * Берёт ли блок этого типа на себя вывод H1 страницы (тогда общий заголовок над блоками не нужен).
     */
    public function consumesPageHeading(string $blockType): bool
    {
        return \in_array($blockType, self::HEADING_BLOCK_TYPES, true);
    }

    /**
     * @param string|null $pageHeading H1 страницы; передаётся только первому блоку страницы
     */
    public function render(PageBlockView $block, bool $aboveFold = false, ?string $pageHeading = null): string
    {
        $template = \sprintf('public/blocks/%s.html.twig', $block->type);
        $context = [
            'block' => $block,
            'above_fold' => $aboveFold,
            'page_h1' => $pageHeading !== null && $this->consumesPageHeading($block->type) ? $pageHeading : null,
        ];

        try {
            return $this->twig->render($template, $context);
        } catch (Error) {
            return $this->twig->render('public/blocks/default.html.twig', $context);
        }
    }
}

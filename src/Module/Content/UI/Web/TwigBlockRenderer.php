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

    /**
     * Для кого показывать блок: пусто — всем.
     */
    private const array AUDIENCES = ['b2c', 'b2b'];

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
            $html = $this->twig->render($template, $context);
        } catch (Error) {
            $html = $this->twig->render('public/blocks/default.html.twig', $context);
        }

        return $this->wrapForAudience($block, $html);
    }

    /**
     * Блок только для частных клиентов (b2c) или только для бизнеса (b2b) получает обёртку с data-audience:
     * переключатель «Частным клиентам / Бизнесу» на странице показывает нужные блоки (docs/50-brand-system.md, раздел 8).
     */
    private function wrapForAudience(PageBlockView $block, string $html): string
    {
        $audience = $block->settings['audience'] ?? null;
        if (!\is_string($audience) || !\in_array($audience, self::AUDIENCES, true) || trim($html) === '') {
            return $html;
        }

        return \sprintf('<div data-audience="%s">%s</div>', $audience, $html);
    }
}

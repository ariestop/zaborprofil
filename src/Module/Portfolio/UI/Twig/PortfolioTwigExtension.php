<?php

declare(strict_types=1);

namespace App\Module\Portfolio\UI\Twig;

use App\Module\Portfolio\Application\Query\PublicPortfolioQuery;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * `portfolio_projects(ids, category, limit)` для блока «Работы из портфолио» (templates/public/blocks/portfolio-projects.html.twig):
 * модуль Content рисует блок шаблоном и не зависит от Portfolio в PHP.
 * `text|pf_excerpt(length)` — начало описания для карточки в ленте (без тегов, по границе слова, с многоточием).
 */
final class PortfolioTwigExtension extends AbstractExtension
{
    private const int MAX_PROJECTS = 24;

    public function __construct(private readonly PublicPortfolioQuery $portfolio)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('portfolio_projects', $this->projects(...))];
    }

    public function getFilters(): array
    {
        return [new TwigFilter('pf_excerpt', self::excerpt(...))];
    }

    public static function excerpt(?string $text, int $length = 110): string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));
        if (mb_strlen($plain) <= $length) {
            return $plain;
        }
        $cut = mb_substr($plain, 0, $length);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $length / 2) {
            $cut = mb_substr($cut, 0, $space);
        }

        // Не rtrim(): он режет по байтам и испортил бы кириллицу перед тире.
        return (string) preg_replace('/[\s,.;:—-]+$/u', '', $cut).'…';
    }

    /**
     * @return list<PortfolioProject>
     */
    public function projects(mixed $ids = [], mixed $category = null, mixed $limit = 6): array
    {
        $ids = \is_array($ids) ? array_values(array_filter($ids, \is_string(...))) : [];
        $limit = \is_int($limit) ? max(1, min(self::MAX_PROJECTS, $limit)) : 6;

        return $this->portfolio->forBlock($ids, \is_string($category) ? trim($category) : null, $limit);
    }
}

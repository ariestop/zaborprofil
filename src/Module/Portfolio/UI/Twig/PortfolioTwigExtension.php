<?php

declare(strict_types=1);

namespace App\Module\Portfolio\UI\Twig;

use App\Module\Portfolio\Application\Query\PublicPortfolioQuery;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `portfolio_projects(ids, category, limit)` для блока «Работы из портфолио» (templates/public/blocks/portfolio-projects.html.twig):
 * модуль Content рисует блок шаблоном и не зависит от Portfolio в PHP.
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

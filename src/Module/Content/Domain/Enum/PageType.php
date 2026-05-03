<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Enum;

enum PageType: string
{
    case Page = 'page';
    case Home = 'home';
    case Landing = 'landing';
    case Service = 'service';
    case Category = 'category';
    case ProductCategoryLanding = 'product_category_landing';
    case MaterialLanding = 'material_landing';
    case PortfolioIndex = 'portfolio_index';
    case PortfolioItem = 'portfolio_item';
    case Contacts = 'contacts';
    case Prices = 'prices';
    case TextPage = 'text_page';
    case SeoLanding = 'seo_landing';
    case System = 'system';
    case SystemPage = 'system_page';

    public function isCommercial(): bool
    {
        return \in_array($this, [
            self::Home,
            self::Landing,
            self::Service,
            self::Category,
            self::ProductCategoryLanding,
            self::MaterialLanding,
            self::SeoLanding,
        ], true);
    }

    public function allowsMultiplePages(): bool
    {
        return !\in_array($this, [
            self::Home,
            self::PortfolioIndex,
            self::Contacts,
            self::Prices,
        ], true);
    }
}

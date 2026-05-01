<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Enum;

enum PageType: string
{
    case Page = 'page';
    case Landing = 'landing';
    case Service = 'service';
    case Category = 'category';
    case PortfolioIndex = 'portfolio_index';
    case Contacts = 'contacts';
    case Prices = 'prices';
    case System = 'system';
}

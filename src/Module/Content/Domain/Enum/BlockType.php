<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Enum;

enum BlockType: string
{
    case Hero = 'hero';
    case Text = 'text';
    case TextImage = 'text_image';
    case Image = 'image';
    case Gallery = 'gallery';
    case Video = 'video';
    case PriceCards = 'price_cards';
    case FeatureGrid = 'feature_grid';
    case Steps = 'steps';
    case Faq = 'faq';
    case CtaForm = 'cta_form';
    case TelegramCta = 'telegram_cta';
    case Contacts = 'contacts';
    case Map = 'map';
    case PortfolioGrid = 'portfolio_grid';
    case SeoText = 'seo_text';
    case HtmlEmbed = 'html_embed';
    case Table = 'table';
    case Accordion = 'accordion';
    case Quote = 'quote';
    case BeforeAfter = 'before_after';
    case CalculatorPlaceholder = 'calculator_placeholder';
    case ReviewCards = 'review_cards';
    case Documents = 'documents';
}

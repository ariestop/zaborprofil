<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Enum;

enum BlockType: string
{
    case Section = 'section';
    case Container = 'container';
    case Grid = 'grid';
    case Columns = 'columns';
    case Spacer = 'spacer';
    case Divider = 'divider';
    case Tabs = 'tabs';

    case HeroClassic = 'hero.classic';
    case HeroCentered = 'hero.centered';
    case HeroSplit = 'hero.split';
    case HeroWithImage = 'hero.with-image';
    case HeroCta = 'hero.cta';
    case HeroMinimal = 'hero.minimal';

    case RichText = 'rich-text';
    case TextWithImage = 'text-with-image';
    case ArticleSection = 'article-section';
    case Benefits = 'benefits';
    case Features = 'features';
    case IconsList = 'icons-list';

    case BeforeAfterStructured = 'before-after';
    case Slider = 'slider';

    case Cta = 'cta';
    case ContactForm = 'contact-form';
    case LeadForm = 'lead-form';
    case CallbackForm = 'callback-form';
    case CalculatorPlaceholderStructured = 'calculator-placeholder';
    case Pricing = 'pricing';
    case Reviews = 'reviews';
    case TrustBadges = 'trust-badges';

    case FenceTypes = 'fence-types';
    case Materials = 'materials';
    case Portfolio = 'portfolio';
    case WorksGallery = 'works-gallery';
    case ServiceCards = 'service-cards';
    case Advantages = 'advantages';
    case InstallationSteps = 'installation-steps';
    case PriceTable = 'price-table';
    case ContactsMap = 'contacts-map';
    case PartnerCta = 'partner-cta';

    case Breadcrumbs = 'breadcrumbs';
    case SitemapSection = 'sitemap-section';
    case RelatedPages = 'related-pages';
    case InternalLinks = 'internal-links';
    case SchemaFaq = 'schema-faq';
    case SchemaLocalBusiness = 'schema-local-business';

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

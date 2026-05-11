<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Enum\BlockType;
use InvalidArgumentException;

final readonly class BlockSchemaRegistry
{
    /**
     * @return list<BlockSchema>
     */
    public function all(): array
    {
        $schemas = [
            new BlockSchema(BlockType::Hero, 'Первый экран (legacy)', 'Первый экран страницы.', ['title'], ['home', 'landing', 'service', 'seo_landing'], ['title' => '', 'text' => '', 'cta' => []], ['layout' => 'default'], 'MVP', 'high', true),
            new BlockSchema(BlockType::Text, 'Текст (legacy)', 'Текстовый блок.', ['text'], ['text_page', 'material_landing'], ['title' => '', 'text' => ''], ['width' => 'prose'], 'MVP', 'medium', true),
            new BlockSchema(BlockType::TextImage, 'Текст + изображение (legacy)', 'Текст с изображением.', ['title', 'text'], ['service', 'material_landing', 'seo_landing'], ['title' => '', 'text' => '', 'image' => null, 'alt' => ''], ['imageSide' => 'right'], 'MVP', 'high', true),
            new BlockSchema(BlockType::Image, 'Изображение (legacy)', 'Одиночное изображение.', ['image', 'alt'], ['portfolio_item', 'material_landing'], ['image' => null, 'alt' => '', 'caption' => ''], ['lazy' => true], 'MVP+', 'medium', true),
            new BlockSchema(BlockType::Gallery, 'Галерея (legacy)', 'Галерея изображений.', ['items'], ['service', 'material_landing', 'portfolio_item'], ['items' => []], ['columns' => 3], 'MVP+', 'high', true),
            new BlockSchema(BlockType::Video, 'Видео (legacy)', 'Видеоблок.', ['url'], ['portfolio_item', 'landing'], ['url' => '', 'title' => ''], ['lazy' => true], 'Later', 'low', true),
            new BlockSchema(BlockType::FeatureGrid, 'Преимущества (legacy)', 'Сетка преимуществ.', ['items'], ['home', 'landing', 'service'], ['items' => []], ['columns' => 3], 'MVP', 'medium', true),
            new BlockSchema(BlockType::PriceCards, 'Карточки цен (legacy)', 'Карточки цен.', ['items'], ['service', 'prices', 'material_landing'], ['items' => []], ['currency' => 'RUB'], 'MVP', 'high', true),
            new BlockSchema(BlockType::Steps, 'Этапы работ (legacy)', 'Этапы работ.', ['items'], ['home', 'service', 'landing'], ['items' => []], ['columns' => 4], 'MVP', 'medium', true),
            new BlockSchema(BlockType::Faq, 'FAQ (legacy)', 'Вопросы и ответы.', ['items'], ['landing', 'service', 'seo_landing'], ['items' => []], ['schemaOrg' => true], 'MVP', 'high', true),
            new BlockSchema(BlockType::CtaForm, 'Форма заявки (legacy)', 'Форма заявки.', ['title'], ['home', 'landing', 'service', 'contacts'], ['title' => '', 'text' => '', 'button' => 'Оставить заявку'], ['source' => 'page_engine'], 'MVP', 'high', true),
            new BlockSchema(BlockType::TelegramCta, 'Telegram CTA (legacy)', 'Переход в Telegram.', ['url'], ['landing', 'contacts'], ['title' => '', 'text' => '', 'url' => ''], ['style' => 'card'], 'MVP+', 'low', true),
            new BlockSchema(BlockType::Contacts, 'Контакты (legacy)', 'Контактные данные.', ['items'], ['contacts'], ['items' => []], ['layout' => 'cards'], 'MVP', 'high', true),
            new BlockSchema(BlockType::Map, 'Карта (legacy)', 'Карта.', ['address'], ['contacts'], ['address' => '', 'embedUrl' => ''], ['height' => 420], 'MVP+', 'medium', true),
            new BlockSchema(BlockType::PortfolioGrid, 'Сетка работ (legacy)', 'Сетка работ.', [], ['home', 'portfolio_index', 'service'], ['items' => []], ['limit' => 6], 'MVP+', 'high', true),
            new BlockSchema(BlockType::SeoText, 'SEO-текст (legacy)', 'SEO-текст.', ['text'], ['landing', 'service', 'seo_landing'], ['title' => '', 'text' => ''], ['collapsed' => false], 'MVP', 'high', true),
            new BlockSchema(BlockType::HtmlEmbed, 'HTML-вставка (legacy)', 'Безопасный HTML/embed.', ['html'], ['text_page'], ['html' => ''], ['sandbox' => true], 'Later', 'medium', true),
            new BlockSchema(BlockType::Table, 'Таблица (legacy)', 'Таблица.', ['columns', 'rows'], ['prices', 'material_landing'], ['columns' => [], 'rows' => []], ['responsive' => 'scroll'], 'MVP', 'medium', true),
            new BlockSchema(BlockType::Accordion, 'Аккордеон (legacy)', 'Аккордеон.', ['items'], ['prices', 'text_page'], ['items' => []], ['multiple' => false], 'MVP+', 'medium', true),
            new BlockSchema(BlockType::CalculatorPlaceholder, 'Калькулятор (legacy)', 'Место под будущий калькулятор.', [], ['landing', 'service'], ['title' => '', 'text' => ''], ['calculator' => null], 'Later', 'low', true),
            new BlockSchema(BlockType::BeforeAfter, 'До/после (legacy)', 'До/после.', ['before', 'after'], ['portfolio_item'], ['before' => null, 'after' => null], ['mode' => 'slider'], 'Later', 'high', true),
            new BlockSchema(BlockType::ReviewCards, 'Отзывы (legacy)', 'Отзывы.', ['items'], ['home', 'landing'], ['items' => []], ['columns' => 3], 'Later', 'medium', true),
            new BlockSchema(BlockType::Documents, 'Документы (legacy)', 'Документы и сертификаты.', ['items'], ['material_landing', 'text_page'], ['items' => []], ['layout' => 'list'], 'Later', 'medium', true),
        ];

        $known = [];
        foreach ($schemas as $schema) {
            $known[$schema->type->value] = true;
        }

        foreach (BlockType::cases() as $type) {
            if (isset($known[$type->value])) {
                continue;
            }

            $schemas[] = new BlockSchema(
                $type,
                $this->structuredLabel($type),
                $this->structuredDescription($type),
                $this->structuredRequiredFields($type),
                [],
                $this->structuredDefaultContent($type),
                $this->structuredDefaultSettings($type),
                'MVP',
                'medium',
            );
        }

        return $schemas;
    }

    public function get(BlockType $type): BlockSchema
    {
        foreach ($this->all() as $schema) {
            if ($schema->type === $type) {
                return $schema;
            }
        }

        throw new InvalidArgumentException(\sprintf('Block type "%s" is not supported.', $type->value));
    }

    /**
     * @param array<string, mixed> $content
     */
    public function validate(BlockType $type, array $content): void
    {
        $this->get($type);

        if ($type === BlockType::HtmlEmbed) {
            $htmlValue = $content['html'] ?? '';
            $html = \is_string($htmlValue) ? $htmlValue : '';
            if (preg_match('/<\s*script\b/i', $html) === 1) {
                throw new InvalidArgumentException('HTML embed cannot contain script tags.');
            }
        }
    }

    private function structuredLabel(BlockType $type): string
    {
        return match ($type) {
            BlockType::Section => 'Секция',
            BlockType::Container => 'Контейнер',
            BlockType::Grid => 'Сетка',
            BlockType::Columns => 'Колонки',
            BlockType::Spacer => 'Отступ',
            BlockType::Divider => 'Разделитель',
            BlockType::Tabs => 'Табы',
            BlockType::HeroClassic => 'Первый экран (классика)',
            BlockType::HeroCentered => 'Первый экран (центр)',
            BlockType::HeroSplit => 'Первый экран (сплит)',
            BlockType::HeroWithImage => 'Первый экран с изображением',
            BlockType::HeroCta => 'Первый экран с CTA',
            BlockType::HeroMinimal => 'Первый экран (минимал)',
            BlockType::RichText => 'Форматированный текст',
            BlockType::TextWithImage => 'Текст с изображением',
            BlockType::ArticleSection => 'Секция статьи',
            BlockType::Benefits => 'Преимущества',
            BlockType::Features => 'Особенности',
            BlockType::IconsList => 'Список с иконками',
            BlockType::Image => 'Изображение',
            BlockType::Gallery => 'Галерея',
            BlockType::BeforeAfterStructured => 'До/После',
            BlockType::Video => 'Видео',
            BlockType::Slider => 'Слайдер',
            BlockType::Cta => 'Призыв к действию',
            BlockType::ContactForm => 'Контактная форма',
            BlockType::LeadForm => 'Лид-форма',
            BlockType::CallbackForm => 'Форма обратного звонка',
            BlockType::CalculatorPlaceholderStructured => 'Калькулятор (заглушка)',
            BlockType::Pricing => 'Тарифы',
            BlockType::Reviews => 'Отзывы',
            BlockType::TrustBadges => 'Бейджи доверия',
            BlockType::FenceTypes => 'Типы заборов',
            BlockType::Materials => 'Материалы',
            BlockType::Portfolio => 'Портфолио',
            BlockType::WorksGallery => 'Галерея работ',
            BlockType::ServiceCards => 'Карточки услуг',
            BlockType::Advantages => 'Преимущества компании',
            BlockType::InstallationSteps => 'Этапы монтажа',
            BlockType::ContactsMap => 'Карта контактов',
            BlockType::PartnerCta => 'Партнерский CTA',
            BlockType::Breadcrumbs => 'Хлебные крошки',
            BlockType::SitemapSection => 'Секция sitemap',
            BlockType::RelatedPages => 'Связанные страницы',
            BlockType::InternalLinks => 'Внутренние ссылки',
            BlockType::PriceTable => 'Таблица цен',
            BlockType::SchemaFaq => 'Schema FAQ',
            BlockType::SchemaLocalBusiness => 'Schema LocalBusiness',
            default => ucwords(str_replace(['.', '-'], ' ', $type->value)),
        };
    }

    private function structuredDescription(BlockType $type): string
    {
        return match ($type) {
            BlockType::Section => 'Секция layout страницы.',
            BlockType::Container => 'Контейнер для ограничения ширины контента.',
            BlockType::Grid => 'Сетка карточек или контента.',
            BlockType::Columns => 'Колонки для контента.',
            BlockType::Spacer => 'Вертикальный отступ между блоками.',
            BlockType::Divider => 'Визуальный разделитель секций.',
            BlockType::Tabs => 'Табы для переключаемого контента.',
            BlockType::HeroClassic, BlockType::HeroCentered, BlockType::HeroSplit, BlockType::HeroWithImage, BlockType::HeroCta, BlockType::HeroMinimal => 'Первый экран страницы.',
            BlockType::RichText => 'Форматированный текстовый блок.',
            BlockType::TextWithImage => 'Текстовый блок с изображением.',
            BlockType::ArticleSection => 'Секция статьи с заголовком и текстом.',
            BlockType::Quote => 'Цитата или отзыв клиента.',
            BlockType::Faq, BlockType::SchemaFaq => 'FAQ-блок с вопросами и ответами.',
            BlockType::Steps, BlockType::InstallationSteps => 'Последовательность шагов.',
            BlockType::Benefits, BlockType::Features, BlockType::Advantages => 'Список преимуществ.',
            BlockType::IconsList => 'Список пунктов с иконками.',
            BlockType::Image, BlockType::Gallery, BlockType::WorksGallery, BlockType::Portfolio => 'Медиа-контент.',
            BlockType::BeforeAfterStructured, BlockType::Slider => 'Сравнение или слайдер изображений.',
            BlockType::Video => 'Видео блок.',
            BlockType::Cta, BlockType::PartnerCta => 'Призыв к действию.',
            BlockType::ContactForm, BlockType::LeadForm, BlockType::CallbackForm => 'Форма заявки.',
            BlockType::CalculatorPlaceholderStructured => 'Заглушка будущего калькулятора.',
            BlockType::Pricing, BlockType::PriceTable => 'Блок с ценами.',
            BlockType::Reviews => 'Отзывы клиентов.',
            BlockType::TrustBadges => 'Бейджи доверия и гарантии.',
            BlockType::FenceTypes, BlockType::Materials, BlockType::ServiceCards => 'Бизнес-контент для каталога услуг.',
            BlockType::ContactsMap, BlockType::Map => 'Карта и контактные данные.',
            BlockType::Breadcrumbs => 'Хлебные крошки для навигации.',
            BlockType::SitemapSection => 'Раздел карты сайта.',
            BlockType::RelatedPages, BlockType::InternalLinks => 'Блок внутренних ссылок.',
            BlockType::SchemaLocalBusiness => 'Structured data LocalBusiness.',
            default => 'Structured block',
        };
    }

    /**
     * @return list<string>
     */
    private function structuredRequiredFields(BlockType $type): array
    {
        return match ($type) {
            BlockType::HeroClassic, BlockType::HeroCentered, BlockType::HeroSplit, BlockType::HeroWithImage, BlockType::HeroCta, BlockType::HeroMinimal => ['title'],
            BlockType::RichText => ['html'],
            BlockType::TextWithImage, BlockType::ArticleSection => ['title', 'text'],
            BlockType::Quote => ['quote'],
            BlockType::Faq, BlockType::SchemaFaq => ['items'],
            BlockType::Steps, BlockType::Benefits, BlockType::Features, BlockType::IconsList => ['items'],
            BlockType::Image => ['src', 'alt'],
            BlockType::Gallery, BlockType::Portfolio, BlockType::WorksGallery => ['items'],
            BlockType::Video => ['url'],
            BlockType::Cta, BlockType::PartnerCta, BlockType::ContactForm, BlockType::LeadForm, BlockType::CallbackForm => ['title'],
            BlockType::Pricing => ['items'],
            BlockType::PriceTable => ['columns', 'rows'],
            BlockType::SchemaLocalBusiness => ['name', 'address'],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredDefaultContent(BlockType $type): array
    {
        return match ($type) {
            BlockType::Section, BlockType::Container => ['title' => 'Новый раздел', 'subtitle' => ''],
            BlockType::Grid => ['columns' => 3],
            BlockType::Columns => ['columns' => 2],
            BlockType::Spacer => ['height' => 24],
            BlockType::Divider => ['label' => ''],
            BlockType::Tabs => ['items' => [['title' => 'Вкладка 1', 'text' => 'Контент вкладки']]],
            BlockType::HeroClassic, BlockType::HeroCentered, BlockType::HeroSplit, BlockType::HeroWithImage, BlockType::HeroCta => [
                'title' => 'Заборы под ключ в Москве',
                'subtitle' => 'Производство, доставка и монтаж',
                'text' => 'Изготовим и установим забор под ваш участок с гарантией.',
                'cta' => ['label' => 'Рассчитать стоимость', 'href' => '#lead-form'],
                'image' => '',
                'imageAlt' => '',
            ],
            BlockType::HeroMinimal => ['title' => 'Заголовок раздела', 'subtitle' => 'Короткий подзаголовок'],
            BlockType::RichText => ['html' => '<p>Добавьте форматированный текст для блока.</p>'],
            BlockType::TextWithImage => ['title' => 'О компании', 'text' => '<p>Текст о преимуществах компании.</p>', 'image' => '', 'imageAlt' => ''],
            BlockType::ArticleSection => ['title' => 'Заголовок секции', 'subtitle' => '', 'text' => '<p>Контент секции статьи.</p>'],
            BlockType::Quote => ['quote' => 'Работа выполнена в срок, качеством довольны.', 'author' => 'Клиент'],
            BlockType::Faq, BlockType::SchemaFaq => ['items' => [['question' => 'Сколько стоит монтаж?', 'answer' => 'Стоимость зависит от типа и длины забора.']]],
            BlockType::Steps, BlockType::InstallationSteps => ['items' => [['title' => 'Замер', 'text' => 'Выезд на объект и расчет.']]],
            BlockType::Benefits, BlockType::Features, BlockType::Advantages => ['items' => [['title' => 'Собственное производство', 'text' => 'Контроль качества на каждом этапе.']]],
            BlockType::IconsList => ['items' => [['icon' => 'check', 'text' => 'Гарантия 5 лет']]],
            BlockType::Image => ['src' => '', 'alt' => 'Изображение', 'caption' => ''],
            BlockType::Gallery, BlockType::WorksGallery, BlockType::Portfolio => ['items' => [['src' => '', 'alt' => 'Фото объекта']]],
            BlockType::BeforeAfterStructured => ['before' => '', 'after' => ''],
            BlockType::Video => ['url' => 'https://www.youtube.com/watch?v=', 'title' => 'Видео о проекте'],
            BlockType::Slider => ['items' => [['src' => '', 'alt' => 'Слайд']]],
            BlockType::Cta, BlockType::PartnerCta => ['title' => 'Оставьте заявку', 'subtitle' => '', 'text' => 'Подготовим персональное предложение.', 'cta' => ['label' => 'Отправить', 'href' => '#lead-form']],
            BlockType::ContactForm, BlockType::LeadForm, BlockType::CallbackForm => ['title' => 'Свяжитесь с нами'],
            BlockType::CalculatorPlaceholderStructured => ['title' => 'Калькулятор скоро будет доступен', 'text' => 'Пока оставьте заявку для расчета менеджером.'],
            BlockType::Pricing => ['items' => [['title' => 'Базовый', 'price' => 'от 3 500 ₽/м', 'features' => ['Монтаж', 'Гарантия']]]],
            BlockType::Reviews => ['items' => [['author' => 'Иван', 'text' => 'Отличная работа и сервис.']]],
            BlockType::TrustBadges => ['items' => [['title' => 'Гарантия 5 лет', 'text' => 'На материалы и монтаж']]],
            BlockType::FenceTypes => ['items' => [['title' => 'Забор жалюзи', 'text' => 'Современный внешний вид', 'image' => '']]],
            BlockType::Materials => ['items' => [['title' => 'Металл', 'text' => 'Оцинкованный профиль']]],
            BlockType::ServiceCards => ['items' => [['title' => 'Монтаж под ключ', 'text' => 'Работы в согласованные сроки', 'href' => '/services/']]],
            BlockType::PriceTable => [
                'columns' => ['Тип', 'Цена'],
                'rows' => [['Профнастил', 'от 2 900 ₽/м'], ['Штакетник', 'от 3 500 ₽/м']],
            ],
            BlockType::ContactsMap => ['address' => 'Москва, ул. Пример, 1', 'embedUrl' => ''],
            BlockType::Breadcrumbs => ['enabled' => true],
            BlockType::SitemapSection => ['title' => 'Разделы сайта'],
            BlockType::RelatedPages, BlockType::InternalLinks => ['items' => [['title' => 'Заборы из профнастила', 'href' => '/zabory-iz-profnastila/']]],
            BlockType::SchemaLocalBusiness => ['name' => 'ЗаборПрофиль', 'address' => 'Москва, ул. Пример, 1', 'phone' => '+7 (999) 000-00-00'],
            default => ['title' => 'Новый блок'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredDefaultSettings(BlockType $type): array
    {
        return match ($type) {
            BlockType::Grid => ['className' => '', 'columns' => 3],
            BlockType::Gallery, BlockType::WorksGallery, BlockType::Portfolio => ['className' => '', 'columns' => 3],
            BlockType::Faq, BlockType::SchemaFaq => ['className' => '', 'schemaOrg' => true],
            BlockType::Image, BlockType::Video => ['className' => '', 'lazy' => true],
            default => ['className' => ''],
        };
    }
}

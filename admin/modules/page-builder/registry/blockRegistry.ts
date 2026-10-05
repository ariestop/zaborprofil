import { z } from 'zod'
import { LEGACY_BLOCK_TYPES, LEGACY_BLOCK_TYPE_ALIASES } from '../types'
import type { BlockDefinition, BuilderBlockType, LegacyBlockType } from '../types'

const textSchema = z.object({
    title: z.string().default(''),
    subtitle: z.string().default(''),
    text: z.string().default(''),
})

const ctaSchema = z.object({
    label: z.string().default('Подробнее'),
    href: z.string().default('#'),
})

const sliderItemSchema = z.object({
    src: z.string().default(''),
    alt: z.string().default(''),
    title: z.string().default(''),
    text: z.string().default(''),
    buttonLabel: z.string().default(''),
    buttonHref: z.string().default(''),
})

const simpleSettingsSchema = z.object({
    className: z.string().default(''),
    /** Якорь секции для ссылок вида /#catalog (необязательно). */
    anchor: z.string().default(''),
    /** Для кого показывать: пусто — всем, b2c — частным клиентам, b2b — бизнесу (переключатель на странице). */
    audience: z.enum(['', 'b2c', 'b2b']).default(''),
})

const priceMatrixGroupSchema = z.object({
    label: z.string(),
    note: z.string().default(''),
    /** Подпись выбора строк, например «Профиль». */
    rowsLabel: z.string().default('Профиль'),
    /** Компактные плитки вместо карточек (например, толщина металла: «0.30 мм — 363 ₽»). */
    compact: z.boolean().default(false),
    /** Сколько вариантов показывать сразу (остальные раскрывает кнопка); для компактных плиток ограничения нет. */
    limit: z.number().int().min(1).max(100).default(6),
    rows: z.array(z.string()).default([]),
    /** Подпись фильтра по полю `group` вариантов, например «Класс покрытия» (пусто — фильтра нет). */
    filterLabel: z.string().default(''),
    options: z
        .array(
            z.object({
                title: z.string(),
                /** Метка группы варианта для фильтра и цветного бейджа, например «ECO». */
                group: z.string().default(''),
                tone: z.enum(['', 'peach', 'amber', 'blue', 'orange', 'green']).default(''),
                /** `chip` — короткая подпись на карточке («гарантия 5 лет»); `-` — не показывать характеристику на карточке. */
                specs: z
                    .array(
                        z.object({
                            label: z.string(),
                            value: z.string(),
                            chip: z.string().default(''),
                        }),
                    )
                    .default([]),
                /** Цена по строкам (профилям); null — вариант для этого профиля не выпускается. */
                prices: z.record(z.string(), z.number().nullable()).default({}),
            }),
        )
        .default([]),
})

const contactOfficeSchema = z.object({
    city: z.string(),
    /** Подпись над названием: «Головной офис», «Дилер». */
    role: z.string().default(''),
    address: z.string().default(''),
    phone: z.string().default(''),
    phoneHref: z.string().default(''),
    email: z.string().default(''),
    mapUrl: z.string().default(''),
    /** IANA-часовой пояс офиса для статуса «открыто сейчас», например Europe/Saratov. */
    timezone: z.string().default(''),
    hours: z
        .array(
            z.object({
                label: z.string(),
                /** Дни недели: 0 — воскресенье … 6 — суббота. */
                days: z.array(z.number().int().min(0).max(6)).default([]),
                open: z.string().default(''),
                close: z.string().default(''),
                note: z.string().default(''),
            }),
        )
        .default([]),
})

const gallerySettingsSchema = simpleSettingsSchema.extend({
    /** Сколько кадров видно сразу; остальные раскрывает кнопка «Показать все фото». */
    visible: z.number().int().min(1).max(200).default(8),
    /** `portrait` — вертикальные кадры (документы, патенты), `contain` — показывать целиком без обрезки. */
    aspect: z.enum(['landscape', 'portrait']).default('landscape'),
    fit: z.enum(['cover', 'contain']).default('cover'),
})

const sliderSettingsSchema = simpleSettingsSchema.extend({
    autoplay: z.boolean().default(true),
    loop: z.boolean().default(true),
    pagination: z.boolean().default(true),
    navigation: z.boolean().default(true),
    delayMs: z.number().int().min(1000).max(15000).default(4500),
    effect: z.enum(['slide', 'fade', 'cards', 'coverflow']).default('slide'),
    pauseOnHover: z.boolean().default(true),
    disableOnInteraction: z.boolean().default(false),
    speedMs: z.number().int().min(100).max(2000).default(500),
    breakpoints: z
        .object({
            mobileSlidesPerView: z.number().int().min(1).max(2).default(1),
            tabletSlidesPerView: z.number().int().min(1).max(3).default(1),
            desktopSlidesPerView: z.number().int().min(1).max(4).default(1),
            mobileSpaceBetween: z.number().int().min(0).max(64).default(8),
            tabletSpaceBetween: z.number().int().min(0).max(64).default(16),
            desktopSpaceBetween: z.number().int().min(0).max(64).default(24),
        })
        .default({
            mobileSlidesPerView: 1,
            tabletSlidesPerView: 1,
            desktopSlidesPerView: 1,
            mobileSpaceBetween: 8,
            tabletSpaceBetween: 16,
            desktopSpaceBetween: 24,
        }),
    a11yLabels: z
        .object({
            prevSlide: z.string().default('Предыдущий слайд'),
            nextSlide: z.string().default('Следующий слайд'),
            paginationBullet: z.string().default('Перейти к слайду {{index}}'),
        })
        .default({
            prevSlide: 'Предыдущий слайд',
            nextSlide: 'Следующий слайд',
            paginationBullet: 'Перейти к слайду {{index}}',
        }),
    cardsEffect: z
        .object({
            perSlideOffset: z.number().int().min(0).max(40).default(6),
            perSlideRotate: z.number().int().min(0).max(30).default(1),
            rotate: z.boolean().default(true),
            slideShadows: z.boolean().default(false),
        })
        .default({
            perSlideOffset: 6,
            perSlideRotate: 1,
            rotate: true,
            slideShadows: false,
        }),
    coverflowEffect: z
        .object({
            rotate: z.number().int().min(0).max(80).default(18),
            stretch: z.number().int().min(-120).max(120).default(0),
            depth: z.number().int().min(0).max(300).default(90),
            modifier: z.number().min(0.1).max(5).default(1),
            slideShadows: z.boolean().default(false),
        })
        .default({
            rotate: 18,
            stretch: 0,
            depth: 90,
            modifier: 1,
            slideShadows: false,
        }),
})

function def(
    type: BuilderBlockType,
    title: string,
    category: BlockDefinition['category'],
    sortOrder: number,
    description: string,
    contentSchema: BlockDefinition['contentSchema'],
    settingsSchema: BlockDefinition['settingsSchema'] = simpleSettingsSchema,
): BlockDefinition {
    return {
        type,
        title,
        category,
        sortOrder,
        description,
        defaults: {
            content: contentSchema.parse({}),
            settings: settingsSchema.parse({}),
        },
        contentSchema,
        settingsSchema,
    }
}

const structuredBlockRegistry: BlockDefinition[] = [
    def(
        'section',
        'Секция',
        'layout',
        10,
        'Базовая секция страницы.',
        z.object({ title: z.string().default('Section') }),
    ),
    def(
        'container',
        'Контейнер',
        'layout',
        20,
        'Контейнер с ограничением ширины.',
        z.object({ title: z.string().default('Container') }),
    ),
    def(
        'grid',
        'Сетка',
        'layout',
        30,
        'Сетка элементов.',
        z.object({ columns: z.number().int().min(1).max(6).default(3) }),
    ),
    def(
        'columns',
        'Колонки',
        'layout',
        40,
        'Колонки контента.',
        z.object({ columns: z.number().int().min(2).max(4).default(2) }),
    ),
    def(
        'spacer',
        'Отступ',
        'layout',
        50,
        'Вертикальный отступ.',
        z.object({ height: z.number().int().min(4).max(320).default(24) }),
    ),
    def(
        'divider',
        'Разделитель',
        'layout',
        60,
        'Разделитель секций.',
        z.object({ label: z.string().default('') }),
    ),
    def(
        'tabs',
        'Табы',
        'layout',
        70,
        'Табы с контентом.',
        z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) }),
    ),
    def(
        'accordion',
        'Аккордеон',
        'layout',
        80,
        'Аккордеон секций.',
        z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) }),
    ),

    def(
        'hero.classic',
        'Первый экран (классика)',
        'hero',
        10,
        'Классический hero-блок.',
        z.object({
            ...textSchema.shape,
            cta: ctaSchema.default({ label: 'Оставить заявку', href: '#lead-form' }),
            image: z.string().default(''),
            imageAlt: z.string().default(''),
        }),
    ),
    def(
        'hero.centered',
        'Первый экран (центр)',
        'hero',
        20,
        'Hero с центрированием.',
        z.object({
            ...textSchema.shape,
            cta: ctaSchema.default({ label: 'Подробнее', href: '#content' }),
        }),
    ),
    def(
        'hero.split',
        'Первый экран (сплит)',
        'hero',
        30,
        'Hero в две колонки.',
        z.object({ ...textSchema.shape, image: z.string().default('') }),
    ),
    def(
        'hero.with-image',
        'Первый экран с изображением',
        'hero',
        40,
        'Hero с изображением.',
        z.object({
            ...textSchema.shape,
            image: z.string().default(''),
            imageAlt: z.string().default(''),
        }),
    ),
    def(
        'hero.cta',
        'Первый экран с CTA',
        'hero',
        50,
        'Hero с усиленным CTA.',
        z.object({
            ...textSchema.shape,
            cta: ctaSchema.default({ label: 'Оставить заявку', href: '#form' }),
        }),
    ),
    def(
        'hero.minimal',
        'Первый экран (минимал)',
        'hero',
        60,
        'Минималистичный hero.',
        z.object({ title: z.string().default('Заголовок'), subtitle: z.string().default('') }),
    ),

    def(
        'rich-text',
        'Форматированный текст',
        'content',
        10,
        'Форматированный текст.',
        z.object({ html: z.string().default('<p>Новый текстовый блок</p>') }),
    ),
    def(
        'text-with-image',
        'Текст с изображением',
        'content',
        20,
        'Текст с картинкой.',
        z.object({
            ...textSchema.shape,
            image: z.string().default(''),
            imageAlt: z.string().default(''),
        }),
    ),
    def(
        'article-section',
        'Секция статьи',
        'content',
        30,
        'Секция статьи.',
        z.object({ ...textSchema.shape }),
    ),
    def(
        'quote',
        'Цитата',
        'content',
        40,
        'Цитата.',
        z.object({ quote: z.string().default('Цитата'), author: z.string().default('') }),
    ),
    def(
        'faq',
        'FAQ',
        'content',
        50,
        'Список вопросов и ответов.',
        z.object({
            title: z.string().default(''),
            items: z.array(z.object({ question: z.string(), answer: z.string() })).default([]),
        }),
    ),
    def(
        'steps',
        'Шаги',
        'content',
        60,
        'Пошаговый блок. У шага можно выбрать фирменную иконку: замер, доставка, монтаж, гарантия.',
        z.object({
            title: z.string().default(''),
            items: z
                .array(
                    z.object({
                        title: z.string(),
                        text: z.string(),
                        icon: z.enum(['', 'measure', 'truck', 'fence', 'shield']).default(''),
                    }),
                )
                .default([]),
        }),
    ),
    def(
        'benefits',
        'Преимущества',
        'content',
        70,
        'Преимущества.',
        z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) }),
    ),
    def(
        'features',
        'Особенности',
        'content',
        80,
        'Фичи/особенности.',
        z.object({
            title: z.string().default(''),
            items: z.array(z.object({ title: z.string(), text: z.string() })).default([]),
        }),
    ),
    def(
        'icons-list',
        'Список с иконками',
        'content',
        90,
        'Список с иконками.',
        z.object({ items: z.array(z.object({ icon: z.string(), text: z.string() })).default([]) }),
    ),

    def(
        'image',
        'Изображение',
        'media',
        10,
        'Одиночное изображение.',
        z.object({
            src: z.string().default(''),
            alt: z.string().default(''),
            caption: z.string().default(''),
        }),
    ),
    def(
        'gallery',
        'Галерея',
        'media',
        20,
        'Галерея изображений.',
        z.object({
            title: z.string().default(''),
            items: z
                .array(
                    z.object({
                        src: z.string(),
                        alt: z.string().default(''),
                        caption: z.string().default(''),
                    }),
                )
                .default([]),
        }),
        gallerySettingsSchema,
    ),
    def(
        'before-after',
        'До/После',
        'media',
        30,
        'Блок сравнения до/после.',
        z.object({ before: z.string().default(''), after: z.string().default('') }),
    ),
    def(
        'video',
        'Видео',
        'media',
        40,
        'Видео-блок.',
        z.object({ url: z.string().default(''), title: z.string().default('') }),
    ),
    def(
        'slider',
        'Слайдер',
        'media',
        50,
        'Слайдер изображений с текстом и CTA на каждом слайде.',
        z.object({
            items: z.array(sliderItemSchema).default([
                sliderItemSchema.parse({
                    src: '',
                    alt: 'Слайд 1',
                    title: 'Заголовок слайда',
                    text: 'Короткое описание слайда.',
                    buttonLabel: 'Подробнее',
                    buttonHref: '#',
                }),
            ]),
        }),
        sliderSettingsSchema,
    ),

    def(
        'cta',
        'Призыв к действию',
        'conversion',
        10,
        'Призыв к действию.',
        z.object({
            ...textSchema.shape,
            cta: ctaSchema.default({ label: 'Оставить заявку', href: '#lead' }),
        }),
    ),
    def(
        'contact-form',
        'Контактная форма',
        'conversion',
        20,
        'Контактная форма.',
        z.object({ title: z.string().default('Свяжитесь с нами') }),
    ),
    def(
        'lead-form',
        'Лид-форма',
        'conversion',
        30,
        'Форма лида.',
        z.object({ title: z.string().default('Оставьте заявку') }),
    ),
    def(
        'callback-form',
        'Форма обратного звонка',
        'conversion',
        40,
        'Форма обратного звонка.',
        z.object({ title: z.string().default('Заказать звонок') }),
    ),
    def(
        'calculator-placeholder',
        'Заглушка калькулятора',
        'conversion',
        50,
        'Заглушка калькулятора.',
        z.object({ title: z.string().default('Калькулятор скоро будет доступен') }),
    ),
    def(
        'pricing',
        'Тарифы',
        'conversion',
        60,
        'Тарифы/пакеты.',
        z.object({
            title: z.string().default(''),
            subtitle: z.string().default(''),
            note: z.string().default(''),
            items: z
                .array(
                    z.object({
                        title: z.string(),
                        price: z.string(),
                        features: z.array(z.string()),
                    }),
                )
                .default([]),
        }),
    ),
    def(
        'reviews',
        'Отзывы',
        'conversion',
        70,
        'Отзывы.',
        z.object({
            items: z.array(z.object({ author: z.string(), text: z.string() })).default([]),
        }),
    ),
    def(
        'trust-badges',
        'Бейджи доверия',
        'conversion',
        80,
        'Бейджи доверия.',
        z.object({
            items: z
                .array(z.object({ title: z.string(), text: z.string().default('') }))
                .default([]),
        }),
    ),

    def(
        'fence-types',
        'Типы заборов',
        'business',
        10,
        'Типы заборов.',
        z.object({
            items: z
                .array(
                    z.object({
                        title: z.string(),
                        text: z.string(),
                        image: z.string().default(''),
                    }),
                )
                .default([]),
        }),
    ),
    def(
        'materials',
        'Материалы',
        'business',
        20,
        'Материалы.',
        z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) }),
    ),
    def(
        'portfolio',
        'Портфолио',
        'business',
        30,
        'Портфолио.',
        z.object({
            title: z.string().default(''),
            items: z
                .array(
                    z.object({
                        title: z.string(),
                        image: z.string().default(''),
                        href: z.string().default('#'),
                    }),
                )
                .default([]),
        }),
    ),
    def(
        'works-gallery',
        'Галерея работ',
        'business',
        40,
        'Галерея работ.',
        z.object({
            items: z.array(z.object({ src: z.string(), alt: z.string().default('') })).default([]),
        }),
    ),
    def(
        'service-cards',
        'Карточки услуг',
        'business',
        50,
        'Карточки услуг.',
        z.object({
            items: z
                .array(
                    z.object({
                        title: z.string(),
                        text: z.string(),
                        href: z.string().default('#'),
                    }),
                )
                .default([]),
        }),
    ),
    def(
        'advantages',
        'Преимущества компании',
        'business',
        60,
        'Преимущества компании.',
        z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) }),
    ),
    def(
        'installation-steps',
        'Этапы монтажа',
        'business',
        70,
        'Этапы монтажа.',
        z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) }),
    ),
    def(
        'price-table',
        'Таблица цен',
        'business',
        80,
        'Таблица цен.',
        z.object({
            title: z.string().default(''),
            columns: z.array(z.string()).default([]),
            rows: z.array(z.array(z.string())).default([]),
        }),
    ),
    def(
        'price-matrix',
        'Прайс-лист с выбором',
        'business',
        85,
        'Посетитель выбирает профиль и сразу видит цены по вариантам; полная таблица раскрывается ниже.',
        z.object({
            title: z.string().default(''),
            subtitle: z.string().default(''),
            unit: z.string().default('₽/м²'),
            groups: z.array(priceMatrixGroupSchema).default([]),
        }),
    ),
    def(
        'fence-configurator',
        'Конфигуратор забора',
        'conversion',
        45,
        'Посетитель выбирает материал, цвет RAL, высоту и длину — видит забор и примерную сумму; кнопка передаёт параметры в заявку.',
        z.object({
            title: z.string().default('Соберите забор и узнайте цену'),
            subtitle: z.string().default(''),
            materials: z
                .array(
                    z.object({
                        title: z.string(),
                        /** Цена за погонный метр забора под ключ для высоты с коэффициентом 1. */
                        pricePerMeter: z.number().min(0).default(0),
                        pattern: z
                            .enum([
                                'profnastil',
                                'profnastil-wide',
                                'shtaketnik',
                                'jaluzi',
                                'setka',
                            ])
                            .default('profnastil'),
                    }),
                )
                .default([]),
            heights: z
                .array(z.object({ label: z.string(), factor: z.number().positive().default(1) }))
                .default([]),
            /** Цвета продукции RAL: hex — экранное приближение для картинки. */
            colors: z
                .array(
                    z.object({
                        ral: z.string(),
                        name: z.string().default(''),
                        hex: z.string().regex(/^#[0-9A-Fa-f]{6}$/),
                    }),
                )
                .default([]),
            gate: z
                .object({
                    label: z.string().default('Ворота и калитка'),
                    price: z.number().min(0).default(0),
                })
                .default({ label: 'Ворота и калитка', price: 0 }),
            length: z
                .object({
                    min: z.number().int().min(1).default(10),
                    max: z.number().int().min(1).default(200),
                    default: z.number().int().min(1).default(40),
                })
                .default({ min: 10, max: 200, default: 40 }),
            cta: z.string().default('Зафиксировать цену'),
            note: z.string().default(''),
        }),
    ),
    def(
        'contacts-map',
        'Контакты и карта',
        'business',
        90,
        'Офисы с режимом работы («открыто сейчас»), карта и реквизиты с кнопками копирования.',
        z.object({
            title: z.string().default(''),
            address: z.string().default(''),
            embedUrl: z.string().default(''),
            offices: z.array(contactOfficeSchema).default([]),
            map: z
                .object({
                    title: z.string().default(''),
                    url: z.string().default(''),
                    embedUrl: z.string().default(''),
                })
                .default({ title: '', url: '', embedUrl: '' }),
            requisites: z
                .object({
                    title: z.string().default(''),
                    legalName: z.string().default(''),
                    note: z.string().default(''),
                    items: z.array(z.object({ label: z.string(), value: z.string() })).default([]),
                })
                .default({ title: '', legalName: '', note: '', items: [] }),
        }),
    ),
    def(
        'partner-cta',
        'Партнерский CTA',
        'business',
        100,
        'CTA для партнеров.',
        z.object({
            ...textSchema.shape,
            cta: ctaSchema.default({ label: 'Стать партнером', href: '#partner' }),
        }),
    ),

    def(
        'breadcrumbs',
        'Хлебные крошки',
        'seo_system',
        10,
        'Хлебные крошки.',
        z.object({ enabled: z.boolean().default(true) }),
    ),
    def(
        'sitemap-section',
        'Секция sitemap',
        'seo_system',
        20,
        'Секция sitemap.',
        z.object({ title: z.string().default('Разделы сайта') }),
    ),
    def(
        'related-pages',
        'Связанные страницы',
        'seo_system',
        30,
        'Связанные страницы.',
        z.object({ items: z.array(z.object({ title: z.string(), href: z.string() })).default([]) }),
    ),
    def(
        'internal-links',
        'Внутренние ссылки',
        'seo_system',
        40,
        'Внутренние ссылки.',
        z.object({ items: z.array(z.object({ title: z.string(), href: z.string() })).default([]) }),
    ),
    def(
        'schema-faq',
        'Schema FAQ',
        'seo_system',
        50,
        'Schema FAQ.',
        z.object({
            items: z.array(z.object({ question: z.string(), answer: z.string() })).default([]),
        }),
    ),
    def(
        'schema-local-business',
        'Schema LocalBusiness',
        'seo_system',
        60,
        'Schema LocalBusiness.',
        z.object({
            name: z.string().default(''),
            address: z.string().default(''),
            phone: z.string().default(''),
        }),
    ),
]

const LEGACY_BLOCK_TITLES: Record<LegacyBlockType, string> = {
    hero: 'Первый экран',
    text: 'Текст',
    text_image: 'Текст + изображение',
    feature_grid: 'Преимущества',
    price_cards: 'Карточки цен',
    cta_form: 'Форма заявки',
    telegram_cta: 'Telegram CTA',
    contacts: 'Контакты',
    map: 'Карта',
    portfolio_grid: 'Сетка работ',
    seo_text: 'SEO-текст',
    html_embed: 'HTML-вставка',
    table: 'Таблица',
    before_after: 'До/после',
    calculator_placeholder: 'Калькулятор',
    review_cards: 'Отзывы',
    documents: 'Документы',
}

// Legacy-блоки хранятся в БД и создаются через content API; их структура не навязывается:
// схемы пропускают любые поля, чтобы сохранение в builder не теряло данные.
const legacyContentSchema = z.looseObject({}) as BlockDefinition['contentSchema']
const legacySettingsSchema = z.looseObject({}) as BlockDefinition['settingsSchema']

function legacyDef(type: LegacyBlockType, sortOrder: number): BlockDefinition {
    const canonicalType = LEGACY_BLOCK_TYPE_ALIASES[type]

    return {
        type,
        title: `${LEGACY_BLOCK_TITLES[type]} (legacy)`,
        category: 'legacy',
        sortOrder,
        description:
            canonicalType === null
                ? 'Устаревший тип блока без прямого аналога. Редактируется через JSON-панель.'
                : `Устаревший тип блока, актуальный аналог: ${canonicalType}. Редактируется через JSON-панель.`,
        legacy: true,
        ...(canonicalType === null ? {} : { canonicalType }),
        defaults: { content: {}, settings: {} },
        contentSchema: legacyContentSchema,
        settingsSchema: legacySettingsSchema,
    }
}

export const legacyBlockRegistry: BlockDefinition[] = LEGACY_BLOCK_TYPES.map((type, index) =>
    legacyDef(type, (index + 1) * 10),
)

export const blockRegistry: BlockDefinition[] = [...structuredBlockRegistry, ...legacyBlockRegistry]

/** Блоки, которые можно добавить на страницу из каталога (legacy только редактируются). */
export const selectableBlockRegistry: BlockDefinition[] = blockRegistry.filter(
    (definition) => definition.legacy !== true,
)

export const blockRegistryByType = new Map(
    blockRegistry.map((definition) => [definition.type, definition]),
)

import { z } from 'zod'
import type { BlockDefinition, BuilderBlockType } from '../types'

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
})

const sliderSettingsSchema = simpleSettingsSchema.extend({
  autoplay: z.boolean().default(true),
  loop: z.boolean().default(true),
  pagination: z.boolean().default(true),
  navigation: z.boolean().default(true),
  delayMs: z.number().int().min(1000).max(15000).default(4500),
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

export const blockRegistry: BlockDefinition[] = [
  def('section', 'Секция', 'layout', 10, 'Базовая секция страницы.', z.object({ title: z.string().default('Section') })),
  def('container', 'Контейнер', 'layout', 20, 'Контейнер с ограничением ширины.', z.object({ title: z.string().default('Container') })),
  def('grid', 'Сетка', 'layout', 30, 'Сетка элементов.', z.object({ columns: z.number().int().min(1).max(6).default(3) })),
  def('columns', 'Колонки', 'layout', 40, 'Колонки контента.', z.object({ columns: z.number().int().min(2).max(4).default(2) })),
  def('spacer', 'Отступ', 'layout', 50, 'Вертикальный отступ.', z.object({ height: z.number().int().min(4).max(320).default(24) })),
  def('divider', 'Разделитель', 'layout', 60, 'Разделитель секций.', z.object({ label: z.string().default('') })),
  def('tabs', 'Табы', 'layout', 70, 'Табы с контентом.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('accordion', 'Аккордеон', 'layout', 80, 'Аккордеон секций.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),

  def('hero.classic', 'Первый экран (классика)', 'hero', 10, 'Классический hero-блок.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Оставить заявку', href: '#lead' }) })),
  def('hero.centered', 'Первый экран (центр)', 'hero', 20, 'Hero с центрированием.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Подробнее', href: '#content' }) })),
  def('hero.split', 'Первый экран (сплит)', 'hero', 30, 'Hero в две колонки.', z.object({ ...textSchema.shape, image: z.string().default('') })),
  def('hero.with-image', 'Первый экран с изображением', 'hero', 40, 'Hero с изображением.', z.object({ ...textSchema.shape, image: z.string().default(''), imageAlt: z.string().default('') })),
  def('hero.cta', 'Первый экран с CTA', 'hero', 50, 'Hero с усиленным CTA.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Оставить заявку', href: '#form' }) })),
  def('hero.minimal', 'Первый экран (минимал)', 'hero', 60, 'Минималистичный hero.', z.object({ title: z.string().default('Заголовок'), subtitle: z.string().default('') })),

  def('rich-text', 'Форматированный текст', 'content', 10, 'Форматированный текст.', z.object({ html: z.string().default('<p>Новый текстовый блок</p>') })),
  def('text-with-image', 'Текст с изображением', 'content', 20, 'Текст с картинкой.', z.object({ ...textSchema.shape, image: z.string().default(''), imageAlt: z.string().default('') })),
  def('article-section', 'Секция статьи', 'content', 30, 'Секция статьи.', z.object({ ...textSchema.shape })),
  def('quote', 'Цитата', 'content', 40, 'Цитата.', z.object({ quote: z.string().default('Цитата'), author: z.string().default('') })),
  def('faq', 'FAQ', 'content', 50, 'Список вопросов и ответов.', z.object({ items: z.array(z.object({ question: z.string(), answer: z.string() })).default([]) })),
  def('steps', 'Шаги', 'content', 60, 'Пошаговый блок.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('benefits', 'Преимущества', 'content', 70, 'Преимущества.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('features', 'Особенности', 'content', 80, 'Фичи/особенности.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('icons-list', 'Список с иконками', 'content', 90, 'Список с иконками.', z.object({ items: z.array(z.object({ icon: z.string(), text: z.string() })).default([]) })),

  def('image', 'Изображение', 'media', 10, 'Одиночное изображение.', z.object({ src: z.string().default(''), alt: z.string().default(''), caption: z.string().default('') })),
  def('gallery', 'Галерея', 'media', 20, 'Галерея изображений.', z.object({ items: z.array(z.object({ src: z.string(), alt: z.string().default('') })).default([]) })),
  def('before-after', 'До/После', 'media', 30, 'Блок сравнения до/после.', z.object({ before: z.string().default(''), after: z.string().default('') })),
  def('video', 'Видео', 'media', 40, 'Видео-блок.', z.object({ url: z.string().default(''), title: z.string().default('') })),
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

  def('cta', 'Призыв к действию', 'conversion', 10, 'Призыв к действию.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Оставить заявку', href: '#lead' }) })),
  def('contact-form', 'Контактная форма', 'conversion', 20, 'Контактная форма.', z.object({ title: z.string().default('Свяжитесь с нами') })),
  def('lead-form', 'Лид-форма', 'conversion', 30, 'Форма лида.', z.object({ title: z.string().default('Оставьте заявку') })),
  def('callback-form', 'Форма обратного звонка', 'conversion', 40, 'Форма обратного звонка.', z.object({ title: z.string().default('Заказать звонок') })),
  def('calculator-placeholder', 'Заглушка калькулятора', 'conversion', 50, 'Заглушка калькулятора.', z.object({ title: z.string().default('Калькулятор скоро будет доступен') })),
  def('pricing', 'Тарифы', 'conversion', 60, 'Тарифы/пакеты.', z.object({ items: z.array(z.object({ title: z.string(), price: z.string(), features: z.array(z.string()) })).default([]) })),
  def('reviews', 'Отзывы', 'conversion', 70, 'Отзывы.', z.object({ items: z.array(z.object({ author: z.string(), text: z.string() })).default([]) })),
  def('trust-badges', 'Бейджи доверия', 'conversion', 80, 'Бейджи доверия.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string().default('') })).default([]) })),

  def('fence-types', 'Типы заборов', 'business', 10, 'Типы заборов.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string(), image: z.string().default('') })).default([]) })),
  def('materials', 'Материалы', 'business', 20, 'Материалы.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('portfolio', 'Портфолио', 'business', 30, 'Портфолио.', z.object({ items: z.array(z.object({ title: z.string(), image: z.string().default(''), href: z.string().default('#') })).default([]) })),
  def('works-gallery', 'Галерея работ', 'business', 40, 'Галерея работ.', z.object({ items: z.array(z.object({ src: z.string(), alt: z.string().default('') })).default([]) })),
  def('service-cards', 'Карточки услуг', 'business', 50, 'Карточки услуг.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string(), href: z.string().default('#') })).default([]) })),
  def('advantages', 'Преимущества компании', 'business', 60, 'Преимущества компании.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('installation-steps', 'Этапы монтажа', 'business', 70, 'Этапы монтажа.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('price-table', 'Таблица цен', 'business', 80, 'Таблица цен.', z.object({ columns: z.array(z.string()).default([]), rows: z.array(z.array(z.string())).default([]) })),
  def('contacts-map', 'Карта контактов', 'business', 90, 'Карта контактов.', z.object({ address: z.string().default(''), embedUrl: z.string().default('') })),
  def('partner-cta', 'Партнерский CTA', 'business', 100, 'CTA для партнеров.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Стать партнером', href: '#partner' }) })),

  def('breadcrumbs', 'Хлебные крошки', 'seo_system', 10, 'Хлебные крошки.', z.object({ enabled: z.boolean().default(true) })),
  def('sitemap-section', 'Секция sitemap', 'seo_system', 20, 'Секция sitemap.', z.object({ title: z.string().default('Разделы сайта') })),
  def('related-pages', 'Связанные страницы', 'seo_system', 30, 'Связанные страницы.', z.object({ items: z.array(z.object({ title: z.string(), href: z.string() })).default([]) })),
  def('internal-links', 'Внутренние ссылки', 'seo_system', 40, 'Внутренние ссылки.', z.object({ items: z.array(z.object({ title: z.string(), href: z.string() })).default([]) })),
  def('schema-faq', 'Schema FAQ', 'seo_system', 50, 'Schema FAQ.', z.object({ items: z.array(z.object({ question: z.string(), answer: z.string() })).default([]) })),
  def('schema-local-business', 'Schema LocalBusiness', 'seo_system', 60, 'Schema LocalBusiness.', z.object({ name: z.string().default(''), address: z.string().default(''), phone: z.string().default('') })),
]

export const blockRegistryByType = new Map(blockRegistry.map((definition) => [definition.type, definition]))

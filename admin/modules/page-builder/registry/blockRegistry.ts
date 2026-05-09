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

const simpleSettingsSchema = z.object({
  className: z.string().default(''),
})

function def(
  type: BuilderBlockType,
  title: string,
  category: BlockDefinition['category'],
  description: string,
  contentSchema: BlockDefinition['contentSchema'],
  settingsSchema: BlockDefinition['settingsSchema'] = simpleSettingsSchema,
): BlockDefinition {
  return {
    type,
    title,
    category,
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
  def('section', 'Секция', 'layout', 'Базовая секция страницы.', z.object({ title: z.string().default('Section') })),
  def('container', 'Контейнер', 'layout', 'Контейнер с ограничением ширины.', z.object({ title: z.string().default('Container') })),
  def('grid', 'Сетка', 'layout', 'Сетка элементов.', z.object({ columns: z.number().int().min(1).max(6).default(3) })),
  def('columns', 'Колонки', 'layout', 'Колонки контента.', z.object({ columns: z.number().int().min(2).max(4).default(2) })),
  def('spacer', 'Отступ', 'layout', 'Вертикальный отступ.', z.object({ height: z.number().int().min(4).max(320).default(24) })),
  def('divider', 'Разделитель', 'layout', 'Разделитель секций.', z.object({ label: z.string().default('') })),
  def('tabs', 'Табы', 'layout', 'Табы с контентом.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('accordion', 'Аккордеон', 'layout', 'Аккордеон секций.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),

  def('hero.classic', 'Первый экран (классика)', 'hero', 'Классический hero-блок.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Оставить заявку', href: '#lead' }) })),
  def('hero.centered', 'Первый экран (центр)', 'hero', 'Hero с центрированием.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Подробнее', href: '#content' }) })),
  def('hero.split', 'Первый экран (сплит)', 'hero', 'Hero в две колонки.', z.object({ ...textSchema.shape, image: z.string().default('') })),
  def('hero.with-image', 'Первый экран с изображением', 'hero', 'Hero с изображением.', z.object({ ...textSchema.shape, image: z.string().default(''), imageAlt: z.string().default('') })),
  def('hero.cta', 'Первый экран с CTA', 'hero', 'Hero с усиленным CTA.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Оставить заявку', href: '#form' }) })),
  def('hero.minimal', 'Первый экран (минимал)', 'hero', 'Минималистичный hero.', z.object({ title: z.string().default('Заголовок'), subtitle: z.string().default('') })),

  def('rich-text', 'Форматированный текст', 'content', 'Форматированный текст.', z.object({ html: z.string().default('<p>Новый текстовый блок</p>') })),
  def('text-with-image', 'Текст с изображением', 'content', 'Текст с картинкой.', z.object({ ...textSchema.shape, image: z.string().default(''), imageAlt: z.string().default('') })),
  def('article-section', 'Секция статьи', 'content', 'Секция статьи.', z.object({ ...textSchema.shape })),
  def('quote', 'Цитата', 'content', 'Цитата.', z.object({ quote: z.string().default('Цитата'), author: z.string().default('') })),
  def('faq', 'FAQ', 'content', 'Список вопросов и ответов.', z.object({ items: z.array(z.object({ question: z.string(), answer: z.string() })).default([]) })),
  def('steps', 'Шаги', 'content', 'Пошаговый блок.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('benefits', 'Преимущества', 'content', 'Преимущества.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('features', 'Особенности', 'content', 'Фичи/особенности.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('icons-list', 'Список с иконками', 'content', 'Список с иконками.', z.object({ items: z.array(z.object({ icon: z.string(), text: z.string() })).default([]) })),

  def('image', 'Изображение', 'media', 'Одиночное изображение.', z.object({ src: z.string().default(''), alt: z.string().default(''), caption: z.string().default('') })),
  def('gallery', 'Галерея', 'media', 'Галерея изображений.', z.object({ items: z.array(z.object({ src: z.string(), alt: z.string().default('') })).default([]) })),
  def('before-after', 'До/После', 'media', 'Блок сравнения до/после.', z.object({ before: z.string().default(''), after: z.string().default('') })),
  def('video', 'Видео', 'media', 'Видео-блок.', z.object({ url: z.string().default(''), title: z.string().default('') })),
  def('slider', 'Слайдер', 'media', 'Слайдер.', z.object({ items: z.array(z.object({ src: z.string(), alt: z.string().default('') })).default([]) })),

  def('cta', 'Призыв к действию', 'conversion', 'Призыв к действию.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Оставить заявку', href: '#lead' }) })),
  def('contact-form', 'Контактная форма', 'conversion', 'Контактная форма.', z.object({ title: z.string().default('Свяжитесь с нами') })),
  def('lead-form', 'Лид-форма', 'conversion', 'Форма лида.', z.object({ title: z.string().default('Оставьте заявку') })),
  def('callback-form', 'Форма обратного звонка', 'conversion', 'Форма обратного звонка.', z.object({ title: z.string().default('Заказать звонок') })),
  def('calculator-placeholder', 'Заглушка калькулятора', 'conversion', 'Заглушка калькулятора.', z.object({ title: z.string().default('Калькулятор скоро будет доступен') })),
  def('pricing', 'Тарифы', 'conversion', 'Тарифы/пакеты.', z.object({ items: z.array(z.object({ title: z.string(), price: z.string(), features: z.array(z.string()) })).default([]) })),
  def('reviews', 'Отзывы', 'conversion', 'Отзывы.', z.object({ items: z.array(z.object({ author: z.string(), text: z.string() })).default([]) })),
  def('trust-badges', 'Бейджи доверия', 'conversion', 'Бейджи доверия.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string().default('') })).default([]) })),

  def('fence-types', 'Типы заборов', 'business', 'Типы заборов.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string(), image: z.string().default('') })).default([]) })),
  def('materials', 'Материалы', 'business', 'Материалы.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('portfolio', 'Портфолио', 'business', 'Портфолио.', z.object({ items: z.array(z.object({ title: z.string(), image: z.string().default(''), href: z.string().default('#') })).default([]) })),
  def('works-gallery', 'Галерея работ', 'business', 'Галерея работ.', z.object({ items: z.array(z.object({ src: z.string(), alt: z.string().default('') })).default([]) })),
  def('service-cards', 'Карточки услуг', 'business', 'Карточки услуг.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string(), href: z.string().default('#') })).default([]) })),
  def('advantages', 'Преимущества компании', 'business', 'Преимущества компании.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('installation-steps', 'Этапы монтажа', 'business', 'Этапы монтажа.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('price-table', 'Таблица цен', 'business', 'Таблица цен.', z.object({ columns: z.array(z.string()).default([]), rows: z.array(z.array(z.string())).default([]) })),
  def('contacts-map', 'Карта контактов', 'business', 'Карта контактов.', z.object({ address: z.string().default(''), embedUrl: z.string().default('') })),
  def('partner-cta', 'Партнерский CTA', 'business', 'CTA для партнеров.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Стать партнером', href: '#partner' }) })),

  def('breadcrumbs', 'Хлебные крошки', 'seo_system', 'Хлебные крошки.', z.object({ enabled: z.boolean().default(true) })),
  def('sitemap-section', 'Секция sitemap', 'seo_system', 'Секция sitemap.', z.object({ title: z.string().default('Разделы сайта') })),
  def('related-pages', 'Связанные страницы', 'seo_system', 'Связанные страницы.', z.object({ items: z.array(z.object({ title: z.string(), href: z.string() })).default([]) })),
  def('internal-links', 'Внутренние ссылки', 'seo_system', 'Внутренние ссылки.', z.object({ items: z.array(z.object({ title: z.string(), href: z.string() })).default([]) })),
  def('schema-faq', 'Schema FAQ', 'seo_system', 'Schema FAQ.', z.object({ items: z.array(z.object({ question: z.string(), answer: z.string() })).default([]) })),
  def('schema-local-business', 'Schema LocalBusiness', 'seo_system', 'Schema LocalBusiness.', z.object({ name: z.string().default(''), address: z.string().default(''), phone: z.string().default('') })),
]

export const blockRegistryByType = new Map(blockRegistry.map((definition) => [definition.type, definition]))

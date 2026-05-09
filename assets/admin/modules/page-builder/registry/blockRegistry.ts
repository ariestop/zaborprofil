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
  def('section', 'Section', 'layout', 'Базовая секция страницы.', z.object({ title: z.string().default('Section') })),
  def('container', 'Container', 'layout', 'Контейнер с ограничением ширины.', z.object({ title: z.string().default('Container') })),
  def('grid', 'Grid', 'layout', 'Сетка элементов.', z.object({ columns: z.number().int().min(1).max(6).default(3) })),
  def('columns', 'Columns', 'layout', 'Колонки контента.', z.object({ columns: z.number().int().min(2).max(4).default(2) })),
  def('spacer', 'Spacer', 'layout', 'Вертикальный отступ.', z.object({ height: z.number().int().min(4).max(320).default(24) })),
  def('divider', 'Divider', 'layout', 'Разделитель секций.', z.object({ label: z.string().default('') })),
  def('tabs', 'Tabs', 'layout', 'Табы с контентом.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('accordion', 'Accordion', 'layout', 'Аккордеон секций.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),

  def('hero.classic', 'Hero Classic', 'hero', 'Классический hero-блок.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Оставить заявку', href: '#lead' }) })),
  def('hero.centered', 'Hero Centered', 'hero', 'Hero с центрированием.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Подробнее', href: '#content' }) })),
  def('hero.split', 'Hero Split', 'hero', 'Hero в две колонки.', z.object({ ...textSchema.shape, image: z.string().default('') })),
  def('hero.with-image', 'Hero With Image', 'hero', 'Hero с изображением.', z.object({ ...textSchema.shape, image: z.string().default(''), imageAlt: z.string().default('') })),
  def('hero.cta', 'Hero CTA', 'hero', 'Hero с усиленным CTA.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Оставить заявку', href: '#form' }) })),
  def('hero.minimal', 'Hero Minimal', 'hero', 'Минималистичный hero.', z.object({ title: z.string().default('Заголовок'), subtitle: z.string().default('') })),

  def('rich-text', 'Rich Text', 'content', 'Форматированный текст.', z.object({ html: z.string().default('<p>Новый текстовый блок</p>') })),
  def('text-with-image', 'Text With Image', 'content', 'Текст с картинкой.', z.object({ ...textSchema.shape, image: z.string().default(''), imageAlt: z.string().default('') })),
  def('article-section', 'Article Section', 'content', 'Секция статьи.', z.object({ ...textSchema.shape })),
  def('quote', 'Quote', 'content', 'Цитата.', z.object({ quote: z.string().default('Цитата'), author: z.string().default('') })),
  def('faq', 'FAQ', 'content', 'Список вопросов и ответов.', z.object({ items: z.array(z.object({ question: z.string(), answer: z.string() })).default([]) })),
  def('steps', 'Steps', 'content', 'Пошаговый блок.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('benefits', 'Benefits', 'content', 'Преимущества.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('features', 'Features', 'content', 'Фичи/особенности.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('icons-list', 'Icons List', 'content', 'Список с иконками.', z.object({ items: z.array(z.object({ icon: z.string(), text: z.string() })).default([]) })),

  def('image', 'Image', 'media', 'Одиночное изображение.', z.object({ src: z.string().default(''), alt: z.string().default(''), caption: z.string().default('') })),
  def('gallery', 'Gallery', 'media', 'Галерея изображений.', z.object({ items: z.array(z.object({ src: z.string(), alt: z.string().default('') })).default([]) })),
  def('before-after', 'Before/After', 'media', 'Блок сравнения до/после.', z.object({ before: z.string().default(''), after: z.string().default('') })),
  def('video', 'Video', 'media', 'Видео-блок.', z.object({ url: z.string().default(''), title: z.string().default('') })),
  def('slider', 'Slider', 'media', 'Слайдер.', z.object({ items: z.array(z.object({ src: z.string(), alt: z.string().default('') })).default([]) })),

  def('cta', 'CTA', 'conversion', 'Призыв к действию.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Оставить заявку', href: '#lead' }) })),
  def('contact-form', 'Contact Form', 'conversion', 'Контактная форма.', z.object({ title: z.string().default('Свяжитесь с нами') })),
  def('lead-form', 'Lead Form', 'conversion', 'Форма лида.', z.object({ title: z.string().default('Оставьте заявку') })),
  def('callback-form', 'Callback Form', 'conversion', 'Форма обратного звонка.', z.object({ title: z.string().default('Заказать звонок') })),
  def('calculator-placeholder', 'Calculator Placeholder', 'conversion', 'Заглушка калькулятора.', z.object({ title: z.string().default('Калькулятор скоро будет доступен') })),
  def('pricing', 'Pricing', 'conversion', 'Тарифы/пакеты.', z.object({ items: z.array(z.object({ title: z.string(), price: z.string(), features: z.array(z.string()) })).default([]) })),
  def('reviews', 'Reviews', 'conversion', 'Отзывы.', z.object({ items: z.array(z.object({ author: z.string(), text: z.string() })).default([]) })),
  def('trust-badges', 'Trust Badges', 'conversion', 'Бейджи доверия.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string().default('') })).default([]) })),

  def('fence-types', 'Fence Types', 'business', 'Типы заборов.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string(), image: z.string().default('') })).default([]) })),
  def('materials', 'Materials', 'business', 'Материалы.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('portfolio', 'Portfolio', 'business', 'Портфолио.', z.object({ items: z.array(z.object({ title: z.string(), image: z.string().default(''), href: z.string().default('#') })).default([]) })),
  def('works-gallery', 'Works Gallery', 'business', 'Галерея работ.', z.object({ items: z.array(z.object({ src: z.string(), alt: z.string().default('') })).default([]) })),
  def('service-cards', 'Service Cards', 'business', 'Карточки услуг.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string(), href: z.string().default('#') })).default([]) })),
  def('advantages', 'Advantages', 'business', 'Преимущества компании.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('installation-steps', 'Installation Steps', 'business', 'Этапы монтажа.', z.object({ items: z.array(z.object({ title: z.string(), text: z.string() })).default([]) })),
  def('price-table', 'Price Table', 'business', 'Таблица цен.', z.object({ columns: z.array(z.string()).default([]), rows: z.array(z.array(z.string())).default([]) })),
  def('contacts-map', 'Contacts Map', 'business', 'Карта контактов.', z.object({ address: z.string().default(''), embedUrl: z.string().default('') })),
  def('partner-cta', 'Partner CTA', 'business', 'CTA для партнеров.', z.object({ ...textSchema.shape, cta: ctaSchema.default({ label: 'Стать партнером', href: '#partner' }) })),

  def('breadcrumbs', 'Breadcrumbs', 'seo_system', 'Хлебные крошки.', z.object({ enabled: z.boolean().default(true) })),
  def('sitemap-section', 'Sitemap Section', 'seo_system', 'Секция sitemap.', z.object({ title: z.string().default('Разделы сайта') })),
  def('related-pages', 'Related Pages', 'seo_system', 'Связанные страницы.', z.object({ items: z.array(z.object({ title: z.string(), href: z.string() })).default([]) })),
  def('internal-links', 'Internal Links', 'seo_system', 'Внутренние ссылки.', z.object({ items: z.array(z.object({ title: z.string(), href: z.string() })).default([]) })),
  def('schema-faq', 'Schema FAQ', 'seo_system', 'Schema FAQ.', z.object({ items: z.array(z.object({ question: z.string(), answer: z.string() })).default([]) })),
  def('schema-local-business', 'Schema LocalBusiness', 'seo_system', 'Schema LocalBusiness.', z.object({ name: z.string().default(''), address: z.string().default(''), phone: z.string().default('') })),
]

export const blockRegistryByType = new Map(blockRegistry.map((definition) => [definition.type, definition]))

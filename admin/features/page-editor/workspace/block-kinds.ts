import { blockRegistryByType } from '../../../modules/page-builder/registry/blockRegistry'
import { LEGACY_BLOCK_TYPE_ALIASES, isLegacyBlockType, type BuilderBlock, type BuilderBlockType } from '../../../modules/page-builder/types'

export type BlockKindCategory = 'hero' | 'text' | 'lists' | 'photo' | 'sales'

export type BlockIconName =
  | 'hero'
  | 'slider'
  | 'text'
  | 'faq'
  | 'features'
  | 'steps'
  | 'gallery'
  | 'portfolio'
  | 'prices'
  | 'cta'
  | 'form'
  | 'generic'

export interface BlockKind {
  type: BuilderBlockType
  name: string
  description: string
  category: BlockKindCategory
  icon: BlockIconName
  /** Что заполнить: короткая подсказка над полями блока. */
  hint: string
  /** Часто используется на страницах услуг: отмечается в каталоге. */
  often: boolean
}

/**
 * Каталог блоков редактора: только виды, у которых есть своё оформление на сайте
 * (templates/public/blocks/*.html.twig). Остальные типы остаются редактируемыми, но в каталог не попадают.
 */
export const CATALOG_KINDS: BlockKind[] = [
  { type: 'hero.classic', name: 'Первый экран', description: 'Заголовок, подзаголовок и кнопка заявки', category: 'hero', icon: 'hero', often: true, hint: 'Заголовок страницы и короткое предложение. Это первый экран: сформулируйте выгоду клиенту.' },
  { type: 'slider', name: 'Слайдер', description: 'Несколько фото с подписями и кнопкой', category: 'hero', icon: 'slider', often: false, hint: 'Широкие фото объектов, на каждом слайде — короткая подпись.' },
  { type: 'rich-text', name: 'Текст', description: 'Подзаголовки, абзацы, списки и ссылки', category: 'text', icon: 'text', often: true, hint: 'Основной текст: 2–3 абзаца с подзаголовком. Замените текст-заготовку на свой.' },
  { type: 'faq', name: 'Вопросы и ответы', description: 'Частые вопросы клиентов с ответами', category: 'text', icon: 'faq', often: true, hint: 'Замените вопросы и ответы на реальные. Пустые вопросы удалите.' },
  { type: 'features', name: 'Преимущества', description: '3–6 карточек: заголовок и пояснение', category: 'lists', icon: 'features', often: true, hint: 'Оставьте 3–6 реальных преимуществ с цифрами и условиями.' },
  { type: 'steps', name: 'Этапы работы', description: 'Пронумерованные шаги от заявки до монтажа', category: 'lists', icon: 'steps', often: true, hint: 'Опишите реальный порядок работы компании, 3–5 шагов.' },
  { type: 'gallery', name: 'Фотогалерея', description: 'Фото из медиатеки с подписями', category: 'photo', icon: 'gallery', often: true, hint: 'Добавьте 6–12 фотографий из медиатеки и заполните подписи (alt).' },
  { type: 'portfolio', name: 'Наши работы', description: 'Объекты: фото, название и ссылка', category: 'photo', icon: 'portfolio', often: false, hint: 'Выберите 3–6 лучших объектов: фото, название и ссылка на страницу работы.' },
  { type: 'price-table', name: 'Цены', description: 'Позиции, единицы и цены «от»', category: 'sales', icon: 'prices', often: true, hint: 'Укажите актуальные цены «от». Обновляйте при каждом изменении прайса.' },
  { type: 'cta', name: 'Призыв к действию', description: 'Короткий призыв и одна кнопка', category: 'sales', icon: 'cta', often: false, hint: 'Короткий призыв с одной кнопкой. «К форме заявки» ведёт к форме на этой странице.' },
  { type: 'contact-form', name: 'Форма заявки', description: 'Имя и телефон, заявка попадает в «Заявки»', category: 'sales', icon: 'form', often: true, hint: 'Форма заявки встаёт в это место страницы. Заявки попадают в раздел «Заявки».' },
]

/** Стартовое содержимое блока из каталога, если пустые значения по умолчанию неудобны для заполнения. */
export const STARTER_CONTENT: Partial<Record<BuilderBlockType, Record<string, unknown>>> = {
  'price-table': { columns: ['Позиция', 'Единица', 'Цена, ₽'], rows: [['', '', '']] },
}

export const KIND_CATEGORIES: Array<{ id: BlockKindCategory | 'all', label: string }> = [
  { id: 'all', label: 'Все' },
  { id: 'hero', label: 'Первый экран' },
  { id: 'text', label: 'Текст' },
  { id: 'lists', label: 'Списки' },
  { id: 'photo', label: 'Фото' },
  { id: 'sales', label: 'Цены и заявки' },
]

const KIND_BY_TYPE = new Map<string, BlockKind>(CATALOG_KINDS.map((kind) => [kind.type, kind]))

/** Устаревшие типы показываются как их современный аналог (иконка, подсказка, предпросмотр). */
export function canonicalType(type: BuilderBlockType): BuilderBlockType {
  if (type === 'seo_text') {
    return 'rich-text'
  }
  if (isLegacyBlockType(type)) {
    return LEGACY_BLOCK_TYPE_ALIASES[type] ?? type
  }

  return type
}

export function kindFor(type: BuilderBlockType): BlockKind | undefined {
  return KIND_BY_TYPE.get(canonicalType(type))
}

export function blockIcon(type: BuilderBlockType): BlockIconName {
  return kindFor(type)?.icon ?? 'generic'
}

/** Машинные имена вроде «hero classic» сохранял старый конструктор: они не годятся для показа. */
function isMachineName(name: string, type: string): boolean {
  const plain = name.trim().toLowerCase()

  return plain === '' || plain === type || plain === type.replace(/[.\-_]/g, ' ').replace(/\s+/g, ' ').trim()
}

/** Название по виду блока: «Первый экран», «Цены»; для остальных — из реестра без пометки (legacy). */
export function kindName(type: BuilderBlockType): string {
  const kind = kindFor(type)
  if (kind !== undefined) {
    return kind.name
  }

  return (blockRegistryByType.get(type)?.title ?? type).replace(/\s*\(legacy\)$/, '')
}

export function blockTitle(block: Pick<BuilderBlock, 'type' | 'name'>): string {
  const name = block.name ?? ''

  return isMachineName(name, block.type) ? kindName(block.type) : name.trim()
}

/** Подпись вида под названием в панели полей: «Таблица цен», «Форматированный текст». */
export function blockTypeLabel(type: BuilderBlockType): string {
  const kind = kindFor(type)
  const title = (blockRegistryByType.get(type)?.title ?? type).replace(/\s*\(legacy\)$/, '')
  if (isLegacyBlockType(type)) {
    return `${title} · старый формат`
  }

  return kind?.description ?? title
}

export function blockHint(block: Pick<BuilderBlock, 'type' | 'name'>): string | null {
  if (canonicalType(block.type) === 'rich-text' && /seo/i.test(block.name ?? '')) {
    return 'Дополнительный текст внизу страницы для поисковых запросов. Не дублируйте текст из других разделов.'
  }

  return kindFor(block.type)?.hint ?? null
}

export function plural(count: number, one: string, few: string, many: string): string {
  const mod10 = count % 10
  const mod100 = count % 100
  if (mod10 === 1 && mod100 !== 11) {
    return one
  }
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return few
  }

  return many
}

export function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

export function asString(value: unknown): string {
  return typeof value === 'string' ? value : ''
}

export function asItems(value: unknown): Record<string, unknown>[] {
  return Array.isArray(value) ? value.filter(isRecord) : []
}

/** Текст без HTML-тегов: для кратких описаний и проверки заготовок. */
export function plainText(html: string): string {
  return html
    .replace(/<\/(p|h[1-6]|li|div)>/gi, ' ')
    .replace(/<br\s*\/?>/gi, ' ')
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/\s+/g, ' ')
    .trim()
}

function shorten(text: string, max = 70): string {
  return text.length > max ? `${text.slice(0, max - 1).trimEnd()}…` : text
}

function firstString(content: Record<string, unknown>): string {
  for (const value of Object.values(content)) {
    if (typeof value === 'string' && plainText(value) !== '') {
      return plainText(value)
    }
  }

  return ''
}

/** Одна строка о содержимом блока для списка «Структура». */
export function blockSummary(block: Pick<BuilderBlock, 'type' | 'content'>): string {
  const content = block.content
  const items = asItems(content.items)
  const titles = (key: string) => items.map((item) => plainText(asString(item[key]))).filter((title) => title !== '')

  switch (canonicalType(block.type)) {
    case 'hero.classic':
      return shorten(plainText(asString(content.title)) || 'Без заголовка')
    case 'rich-text':
      return shorten(plainText(asString(content.html) || asString(content.text) || asString(content.richText)) || 'Пустой текст')
    case 'features':
    case 'steps': {
      if (items.length === 0) {
        return 'Нет пунктов'
      }
      const noun = block.type === 'steps' ? plural(items.length, 'шаг', 'шага', 'шагов') : plural(items.length, 'пункт', 'пункта', 'пунктов')
      return shorten(`${items.length} ${noun} · ${titles('title').join(', ')}`)
    }
    case 'faq':
      return items.length === 0 ? 'Нет вопросов' : shorten(`${items.length} ${plural(items.length, 'вопрос', 'вопроса', 'вопросов')} · ${titles('question')[0] ?? ''}`)
    case 'gallery': {
      const photos = items.filter((item) => asString(item.src ?? item.image) !== '').length
      return photos === 0 ? 'Фото не добавлены' : `${photos} фото`
    }
    case 'portfolio':
      return items.length === 0 ? 'Работы не выбраны' : shorten(`${items.length} ${plural(items.length, 'работа', 'работы', 'работ')} · ${titles('title').join(', ')}`)
    case 'slider':
      return items.length === 0 ? 'Нет слайдов' : `${items.length} ${plural(items.length, 'слайд', 'слайда', 'слайдов')}`
    case 'price-table': {
      const rows = Array.isArray(content.rows) ? content.rows.filter(Array.isArray) as unknown[][] : []
      if (rows.length === 0) {
        return 'Нет строк'
      }
      const first = rows[0] ?? []
      const label = asString(first[0])
      const price = asString(first[first.length - 1])
      return shorten(`${rows.length} ${plural(rows.length, 'строка', 'строки', 'строк')} · «${label}» ${price}`)
    }
    case 'cta':
      return shorten(plainText(asString(content.title)) || 'Без заголовка')
    case 'contact-form':
      return shorten(`«${plainText(asString(content.title)) || 'Оставьте заявку'}» → раздел «Заявки»`)
    default:
      return shorten(firstString(content) || 'Без текста')
  }
}

/**
 * Тексты-заготовки из системных шаблонов страниц (миграция Version20261014153700):
 * инструкции «Укажите…», «Замените…» и обезличенные «Позиция 1», «Подзаголовок».
 */
const PLACEHOLDER_PATTERNS: RegExp[] = [
  /(^|[\s«(])(Укажите|укажите|Опишите|Расскажите|Замените|Уточните|Сравните)\s/,
  /^Позиция \d+$/,
  /^(Подзаголовок|Название услуги|Название материала|Заголовок страницы|Заголовок под поисковый запрос|Заголовок слайда)$/,
  /Основной текст страницы/,
  /Что получает клиент и за какое время/,
  /Для каких участков подходит/,
  /Текст для поискового продвижения/,
  /Ответ на запрос в одном предложении/,
  /Добавьте форматированный текст/,
  /^Новый текстовый блок$/,
]

function collectStrings(value: unknown, out: string[]): void {
  if (typeof value === 'string') {
    const text = plainText(value)
    if (text !== '') {
      out.push(text)
    }
    return
  }
  if (Array.isArray(value)) {
    value.forEach((child) => collectStrings(child, out))
    return
  }
  if (isRecord(value)) {
    Object.values(value).forEach((child) => collectStrings(child, out))
  }
}

export function isPlaceholderText(text: string): boolean {
  const plain = plainText(text)

  return PLACEHOLDER_PATTERNS.some((pattern) => pattern.test(plain))
}

const MEDIA_LIST_TYPES = new Set<BuilderBlockType>(['gallery', 'portfolio', 'slider'])

/** «Заготовка»: в блоке остались тексты из шаблона или пустой список фото. */
export function isPlaceholderBlock(block: Pick<BuilderBlock, 'type' | 'content'>): boolean {
  if (MEDIA_LIST_TYPES.has(canonicalType(block.type)) && asItems(block.content.items).length === 0) {
    return true
  }

  const strings: string[] = []
  collectStrings(block.content, strings)

  return strings.some((text) => PLACEHOLDER_PATTERNS.some((pattern) => pattern.test(text)))
}

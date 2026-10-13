import type { PageStatus } from '../../entities/page/api'

export const pageStatusLabels: Record<PageStatus, string> = {
  draft: 'Черновик',
  review: 'На проверке',
  approved: 'Одобрено',
  published: 'Опубликовано',
  scheduled: 'Запланировано',
  unpublished: 'Снято с публикации',
  archived: 'Архив',
  deleted: 'Удалено',
}

export const pageStatusTransitions: Record<PageStatus, PageStatus[]> = {
  draft: ['review', 'approved', 'published', 'archived', 'deleted'],
  review: ['approved', 'draft', 'archived', 'deleted'],
  approved: ['published', 'scheduled', 'draft', 'archived', 'deleted'],
  published: ['unpublished', 'scheduled', 'archived', 'deleted'],
  scheduled: ['published', 'draft', 'deleted'],
  unpublished: ['draft', 'published', 'archived', 'deleted'],
  archived: ['draft', 'deleted'],
  deleted: ['draft'],
}

function statusLabel(value: string): string {
  return value in pageStatusLabels ? pageStatusLabels[value as PageStatus] : value
}

/** Переводит ошибки смены статуса из API (тексты домена на английском) в понятное сообщение. */
export function describePageStatusError(message: string): string {
  const transition = /cannot transition from "([a-z]+)" to "([a-z]+)"/.exec(message)
  if (transition !== null) {
    return `нельзя перевести из «${statusLabel(transition[1])}» в «${statusLabel(transition[2])}»`
  }
  const role = /cannot set page status "([a-z]+)"/.exec(message)
  if (role !== null) {
    return `нет прав на статус «${statusLabel(role[1])}»`
  }

  return message
}

/** Переходы, для которых в шапке редактора нет отдельной кнопки (их берёт на себя слот публикации). */
const quickStatusTransitions = new Set<PageStatus>(['review', 'approved', 'published', 'scheduled', 'deleted'])

export function canPublishFrom(status: PageStatus): boolean {
  return status !== 'published' && pageStatusTransitions[status].includes('published')
}

export function quickStatusActions(status: PageStatus): PageStatus[] {
  return pageStatusTransitions[status].filter((next) => !quickStatusTransitions.has(next))
}

export function statusTone(status: PageStatus): 'neutral' | 'success' | 'warning' {
  if (status === 'published') {
    return 'success'
  }

  return status === 'draft' || status === 'review' || status === 'scheduled' ? 'warning' : 'neutral'
}

export const pageTypeLabels: Record<string, string> = {
  home: 'Главная',
  landing: 'Посадочная страница',
  service: 'Услуга',
  product_category_landing: 'Категория товаров',
  material_landing: 'Материал',
  portfolio_index: 'Список работ',
  portfolio_item: 'Работа в портфолио',
  contacts: 'Контакты',
  prices: 'Цены',
  text_page: 'Текстовая страница',
  seo_landing: 'SEO-посадочная',
  system_page: 'Системная страница',
}

export const visibilityLabels: Record<string, string> = {
  public: 'Публичная',
  hidden: 'Скрытая',
  unlisted: 'Доступна по ссылке',
}

/** Что видит посетитель. Как и индексация, действует сразу после сохранения, без переопубликации. */
export const visibilityHints: Record<string, string> = {
  public: 'Открывается на сайте, попадает в sitemap и индексируется (если индексация разрешена).',
  hidden: 'На сайте не открывается (404), в sitemap и хлебных крошках не появляется. Предпросмотр работает.',
  unlisted: 'Открывается по прямой ссылке, но не индексируется (noindex) и не попадает в sitemap.',
}

export const templateLabels: Record<string, string> = {
  home_default: 'Главная страница',
  service_landing: 'Страница услуги',
  material_landing: 'Страница материала',
  portfolio_index: 'Список работ',
  contacts: 'Контакты',
  prices: 'Цены',
  text_page: 'Текстовая страница',
  seo_landing: 'SEO-посадочная',
  default: 'Без шаблона',
}

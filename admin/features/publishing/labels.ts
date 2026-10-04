import type { WorkflowStatus } from './types'

export const statusLabels: Record<WorkflowStatus, string> = {
  draft: 'Черновик',
  review: 'На проверке',
  approved: 'Одобрено',
  published: 'Опубликовано',
  scheduled: 'Запланировано',
  unpublished: 'Снято с публикации',
  archived: 'Архив',
  deleted: 'Удалено',
}

export const statusActionLabels: Record<WorkflowStatus, string> = {
  draft: 'Вернуть в черновик',
  review: 'Отправить на проверку',
  approved: 'Одобрить',
  published: 'Опубликовать',
  scheduled: 'Запланировать…',
  unpublished: 'Снять с публикации',
  archived: 'В архив',
  deleted: 'Удалить',
}

export const eventLabels: Record<string, string> = {
  status_changed: 'Смена статуса',
  published: 'Публикация',
  unpublished: 'Снятие с публикации',
  scheduled: 'Запланировано',
  schedule_cancelled: 'Расписание отменено',
  scheduled_published: 'Опубликовано по расписанию',
  scheduled_unpublished: 'Снято по расписанию',
  schedule_failed: 'Сбой расписания',
  rolled_back: 'Откат ревизии',
  archived: 'Архивирование',
}

export const fieldLabels: Record<string, string> = {
  title: 'Заголовок',
  h1: 'H1',
  slug: 'Slug',
  path: 'URL',
  type: 'Тип',
  template: 'Шаблон',
  isIndexable: 'Индексация',
  metaTitle: 'Meta title',
  metaDescription: 'Meta description',
  canonicalUrl: 'Canonical',
  ogTitle: 'OG title',
  ogDescription: 'OG description',
  ogImage: 'OG image',
  ogType: 'OG type',
  jsonLd: 'JSON-LD',
  visibility: 'Видимость',
  sortOrder: 'Порядок',
  name: 'Название',
  isEnabled: 'Включён',
  order: 'Позиция',
}

export const blockStatusLabels = {
  added: 'Добавлен',
  removed: 'Удалён',
  changed: 'Изменён',
  unchanged: 'Без изменений',
} as const

export function fieldLabel(field: string): string {
  if (field.startsWith('content.')) return `Контент: ${field.slice('content.'.length)}`
  if (field.startsWith('settings.')) return `Настройки: ${field.slice('settings.'.length)}`

  return fieldLabels[field] ?? field
}

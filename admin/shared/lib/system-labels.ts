/**
 * Человекочитаемые подписи для системных разделов: журнал действий, предупреждения, окружение.
 * Сервер отдаёт технические значения (имена классов, коды), а администратору нужны понятные слова.
 */

const ACTION_LABELS: Record<string, string> = {
  create: 'Создание',
  created: 'Создание',
  update: 'Изменение',
  updated: 'Изменение',
  delete: 'Удаление',
  deleted: 'Удаление',
  publish: 'Публикация',
  unpublish: 'Снятие с публикации',
  login: 'Вход',
  logout: 'Выход',
}

const ENTITY_LABELS: Record<string, string> = {
  Page: 'Страница',
  PageBlock: 'Блок страницы',
  PageRevision: 'Ревизия страницы',
  Lead: 'Заявка',
  LeadEvent: 'Событие заявки',
  Redirect: 'Редирект',
  MediaAsset: 'Файл медиатеки',
  AdminUser: 'Пользователь',
  User: 'Пользователь',
  Setting: 'Настройка',
  Menu: 'Меню',
  MenuItem: 'Пункт меню',
}

const ENVIRONMENT_NAMES: Record<string, string> = {
  prod: 'боевой сайт',
  staging: 'тестовый стенд',
  dev: 'разработка',
  test: 'тесты',
}

export function auditActionLabel(action: string): string {
  return ACTION_LABELS[action.toLowerCase()] ?? action
}

/** `App\Module\Content\Domain\Entity\Page` → «Страница». */
export function auditEntityLabel(entityType: string): string {
  const shortName = entityType.split('\\').pop() ?? entityType

  return ENTITY_LABELS[shortName] ?? shortName
}

function stringifyValue(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  if (typeof value === 'string') {
    return value
  }
  if (typeof value === 'number' || typeof value === 'boolean') {
    return String(value)
  }

  return JSON.stringify(value)
}

export interface AuditChange {
  field: string
  before: string
  after: string
}

/** Изменённые поля без служебного `updatedAt`, который меняется при каждом сохранении. */
export function auditChanges(oldValues: Record<string, unknown>, newValues: Record<string, unknown>): AuditChange[] {
  const fields = Array.from(new Set([...Object.keys(oldValues), ...Object.keys(newValues)]))
    .filter((field) => field !== 'updatedAt')

  return fields.map((field) => ({
    field,
    before: stringifyValue(oldValues[field]),
    after: stringifyValue(newValues[field]),
  }))
}

export function environmentName(appEnv: string): string {
  return ENVIRONMENT_NAMES[appEnv] ?? appEnv
}

export function warningSeverityLabel(severity: string): string {
  return severity === 'critical' ? 'Критично' : 'Предупреждение'
}

export function formatBytes(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes < 0) {
    return '—'
  }
  const units = ['Б', 'КБ', 'МБ', 'ГБ']
  let value = bytes
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit += 1
  }

  return `${value.toLocaleString('ru-RU', { maximumFractionDigits: unit === 0 ? 0 : 1 })} ${units[unit]}`
}

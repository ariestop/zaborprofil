import type { LeadItem, LeadStatus } from '../../types/api'

export type LeadSortField = 'createdAt' | 'updatedAt' | 'name' | 'status' | 'source'
export type LeadAssigneeFilter = 'all' | 'me' | 'none' | string

export interface LeadFilters {
  q: string
  status: LeadStatus | 'all'
  source: string
  from: string
  to: string
  assignee: LeadAssigneeFilter
  /** Только заявки юридических лиц и ИП. */
  b2b: boolean
  /** Только новые заявки без ответа дольше указанного числа часов; 0 — фильтр выключен. */
  waitingHours: number
}

export interface LeadListParams extends LeadFilters {
  sort: LeadSortField
  direction: 'asc' | 'desc'
  page: number
  perPage: number
}

export interface LeadListResponse {
  items: LeadItem[]
  total: number
  page: number
  perPage: number
  pages: number
  counts: {
    total: number
    byStatus: Record<LeadStatus, number>
  }
  statuses: LeadStatus[]
  sources: string[]
}

export interface LeadSummary {
  total: number
  new: number
  byStatus: Record<LeadStatus, number>
  statuses: LeadStatus[]
}

/** Данные для сводки: заявки по дням, завершённые за неделю и самая давняя новая заявка. */
export interface LeadDashboard {
  daily: Array<{ date: string, count: number }>
  createdLast14Days: number
  doneLastWeek: number
  oldestNewAt: string | null
}

export interface LeadAssigneeOption {
  id: string
  email: string
  /** «Фамилия Имя» из профиля; `null`, если не заполнено. */
  name: string | null
}

export const LEAD_STATUS_LABELS: Record<LeadStatus, string> = {
  new: 'Новые',
  in_progress: 'В работе',
  done: 'Завершены',
  spam: 'Спам',
}

export const LEAD_STATUS_SINGULAR: Record<LeadStatus, string> = {
  new: 'Новая',
  in_progress: 'В работе',
  done: 'Завершена',
  spam: 'Спам',
}

export const LEAD_SOURCE_LABELS: Record<string, string> = {
  public_form: 'Форма на сайте',
  public_page_form: 'Форма на странице',
  callback: 'Обратный звонок',
  phone_call: 'Звонок',
  calc_form: 'Форма «Рассчитать стоимость»',
  telegram: 'Telegram-бот',
  page_engine: 'Конструктор страниц',
}

export function leadSourceLabel(source: string): string {
  return LEAD_SOURCE_LABELS[source] ?? source
}

export function leadStatusLabel(status: string): string {
  return LEAD_STATUS_SINGULAR[status as LeadStatus] ?? status
}

export function leadStatusTone(status: LeadStatus): 'neutral' | 'success' | 'warning' {
  if (status === 'done') {
    return 'success'
  }

  return status === 'new' || status === 'spam' ? 'warning' : 'neutral'
}

export interface LeadNextAction {
  status: LeadStatus
  label: string
}

/** Основное действие в карточке: следующий шаг работы с заявкой в один клик. */
export function nextLeadAction(status: LeadStatus): LeadNextAction | null {
  switch (status) {
    case 'new':
      return { status: 'in_progress', label: 'Взять в работу' }
    case 'in_progress':
      return { status: 'done', label: 'Завершить' }
    case 'done':
      return { status: 'in_progress', label: 'Вернуть в работу' }
    case 'spam':
      return { status: 'new', label: 'Это не спам' }
    default:
      return null
  }
}

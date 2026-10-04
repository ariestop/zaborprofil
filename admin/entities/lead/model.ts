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

export interface LeadAssigneeOption {
  id: string
  email: string
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
  page_engine: 'Конструктор страниц',
}

export function leadSourceLabel(source: string): string {
  return LEAD_SOURCE_LABELS[source] ?? source
}

export function leadStatusLabel(status: string): string {
  return LEAD_STATUS_SINGULAR[status as LeadStatus] ?? status
}

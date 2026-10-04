import { useCallback, useState } from 'react'
import type { LeadPeriod } from '../../entities/lead/presentation'

const STORAGE_KEY = 'admin.crm.views'
const MAX_VIEWS = 8

/** Сохранённый набор фильтров. Период хранится как пресет («сегодня»), а не как даты, чтобы вид не устаревал. */
export interface LeadSavedView {
  id: string
  name: string
  status: string
  source: string
  assignee: string
  b2b: boolean
  waitingHours: number
  period: LeadPeriod
  from: string
  to: string
}

function isSavedView(value: unknown): value is LeadSavedView {
  if (value === null || typeof value !== 'object') {
    return false
  }
  const view = value as Record<string, unknown>

  return typeof view.id === 'string' && typeof view.name === 'string' && typeof view.status === 'string'
    && typeof view.source === 'string' && typeof view.assignee === 'string' && typeof view.b2b === 'boolean'
    && typeof view.waitingHours === 'number' && typeof view.period === 'string'
    && typeof view.from === 'string' && typeof view.to === 'string'
}

function readViews(): LeadSavedView[] {
  try {
    const parsed: unknown = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '[]')

    return Array.isArray(parsed) ? parsed.filter(isSavedView).slice(0, MAX_VIEWS) : []
  } catch {
    return []
  }
}

function writeViews(views: LeadSavedView[]): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(views))
  } catch {
    // Хранилище недоступно — виды живут только до перезагрузки страницы.
  }
}

/** Сохранённые виды списка заявок; хранятся в браузере администратора. */
export function useLeadSavedViews() {
  const [views, setViews] = useState<LeadSavedView[]>(readViews)

  const add = useCallback((view: Omit<LeadSavedView, 'id'>) => {
    setViews((current) => {
      const next = [...current, { ...view, id: crypto.randomUUID() }].slice(-MAX_VIEWS)
      writeViews(next)

      return next
    })
  }, [])

  const remove = useCallback((id: string) => {
    setViews((current) => {
      const next = current.filter((view) => view.id !== id)
      writeViews(next)

      return next
    })
  }, [])

  return { views, add, remove, limit: MAX_VIEWS }
}

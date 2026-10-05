import type { LeadDashboard } from '../../entities/lead/model'
import type { ContentPageItem, SystemObservabilityResponse } from '../../types/api'

/** Приветствие по времени суток: «Доброе утро», «Добрый день», «Добрый вечер», «Доброй ночи». */
export function greeting(now: Date = new Date()): string {
  const hour = now.getHours()
  if (hour >= 5 && hour < 12) {
    return 'Доброе утро'
  }
  if (hour >= 12 && hour < 18) {
    return 'Добрый день'
  }
  if (hour >= 18 && hour < 23) {
    return 'Добрый вечер'
  }

  return 'Доброй ночи'
}

/** «Воскресенье, 4 октября». */
export function todayLabel(now: Date = new Date()): string {
  const label = now.toLocaleDateString('ru-RU', { weekday: 'long', day: 'numeric', month: 'long' })

  return label.charAt(0).toUpperCase() + label.slice(1)
}

export interface DailyBar {
  date: string
  count: number
  /** Высота столбика в пикселях; пустой день — тонкая линия, чтобы ряд не «проваливался». */
  height: number
  today: boolean
}

export const CHART_HEIGHT = 110

/** Высота столбиков масштабируется по самому «загруженному» дню; последний день — «сегодня». */
export function buildDailyBars(daily: LeadDashboard['daily'], maxHeight: number = CHART_HEIGHT - 12): DailyBar[] {
  const max = Math.max(1, ...daily.map((day) => day.count))

  return daily.map((day, index) => ({
    date: day.date,
    count: day.count,
    height: day.count === 0 ? 2 : Math.max(6, Math.round((day.count / max) * maxHeight)),
    today: index === daily.length - 1,
  }))
}

/** «21 сент.» для подписи начала периода. */
export function shortDate(isoDate: string): string {
  const [year, month, day] = isoDate.split('-').map(Number)
  if (year === undefined || month === undefined || day === undefined) {
    return isoDate
  }

  return new Date(year, month - 1, day).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}

export interface PublicationCount {
  published: number
  total: number
}

/** «Опубликовано 18 / 26»: удалённые и архивные страницы в общее число не входят. */
export function countPublications(pages: Pick<ContentPageItem, 'status'>[]): PublicationCount {
  const live = pages.filter((page) => page.status !== 'deleted' && page.status !== 'archived')

  return { published: live.filter((page) => page.status === 'published').length, total: live.length }
}

export type SiteTone = 'ok' | 'warning' | 'critical' | 'neutral'

export interface SiteStateRow {
  id: 'platform' | 'build' | 'backups' | 'disk' | 'queue'
  label: string
  value: string
  tone: SiteTone
}

export interface SiteStateInput {
  platformOk?: boolean
  phpVersion?: string
  buildStatus?: 'idle' | 'running' | 'success' | 'failed'
  /** `false` — каталог копий пуст; `undefined` — данных нет. */
  hasBackups?: boolean
  disk?: SystemObservabilityResponse['disk']['status']
  failedMessages?: number
}

/** Строки блока «Состояние сайта»: показываются только те, по которым есть данные. */
export function buildSiteState({ platformOk, phpVersion, buildStatus, hasBackups, disk, failedMessages }: SiteStateInput): SiteStateRow[] {
  const rows: SiteStateRow[] = []

  if (platformOk !== undefined) {
    rows.push({
      id: 'platform',
      label: 'Статус платформы',
      value: platformOk ? `OK${phpVersion === undefined ? '' : ` · PHP ${phpVersion}`}` : 'есть сбои',
      tone: platformOk ? 'ok' : 'critical',
    })
  }

  if (buildStatus !== undefined) {
    rows.push({
      id: 'build',
      label: 'Сборка админки',
      value: buildStatus === 'failed' ? 'ошибка' : buildStatus === 'running' ? 'идёт сборка' : buildStatus === 'success' ? 'готова' : 'не запускалась',
      tone: buildStatus === 'failed' ? 'critical' : buildStatus === 'running' ? 'warning' : buildStatus === 'success' ? 'ok' : 'neutral',
    })
  }

  if (hasBackups !== undefined) {
    rows.push({ id: 'backups', label: 'Резервные копии', value: hasBackups ? 'есть' : 'не найдены', tone: hasBackups ? 'ok' : 'critical' })
  }

  if (disk !== undefined) {
    rows.push({
      id: 'disk',
      label: 'Место на диске',
      value: disk === 'fail' ? 'нет места' : disk === 'warning' ? 'заканчивается' : 'достаточно',
      tone: disk === 'fail' ? 'critical' : disk === 'warning' ? 'warning' : 'ok',
    })
  }

  if (failedMessages !== undefined) {
    rows.push({
      id: 'queue',
      label: 'Очереди задач',
      value: `${failedMessages} с ошибкой`,
      tone: failedMessages > 0 ? 'critical' : 'ok',
    })
  }

  return rows
}

import type { SystemObservabilityResponse } from '../../types/api'

export type ObservabilityTone = 'ok' | 'warning' | 'critical'

export interface ObservabilityTile {
  id: 'server-errors' | 'queue' | 'disk'
  label: string
  value: string
  hint: string
  tone: ObservabilityTone
  href: string
}

const UNITS = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ']

export function formatBytes(bytes: number | null): string {
  if (bytes === null || !Number.isFinite(bytes) || bytes < 0) {
    return '—'
  }

  let value = bytes
  let unit = 0
  while (value >= 1024 && unit < UNITS.length - 1) {
    value /= 1024
    unit += 1
  }

  const rounded = unit === 0 ? String(Math.round(value)) : value.toFixed(value >= 10 ? 0 : 1).replace('.', ',')

  return `${rounded} ${UNITS[unit]}`
}

/** Плитки «Мониторинг» для сводки: 5xx, очередь и свободное место на диске. */
export function buildObservabilityTiles(data: SystemObservabilityResponse): ObservabilityTile[] {
  const { serverErrors, queue, disk } = data

  return [
    {
      id: 'server-errors',
      label: 'Ошибки 5xx за 24 ч',
      value: String(serverErrors.last24Hours),
      hint: serverErrors.lastHour > 0 ? `за последний час: ${serverErrors.lastHour}` : 'за последний час нет',
      tone: serverErrors.lastHour > 0 ? 'critical' : serverErrors.last24Hours > 0 ? 'warning' : 'ok',
      href: '/admin/system/logs',
    },
    {
      id: 'queue',
      label: 'Очередь задач',
      value: String(queue.pending),
      hint: queue.failed > 0 ? `с ошибкой: ${queue.failed}` : 'сбоев нет',
      tone: queue.failed > 0 ? 'critical' : 'ok',
      href: '/admin/system/queues',
    },
    {
      id: 'disk',
      label: 'Свободно на диске',
      value: formatBytes(disk.freeBytes),
      hint: disk.usedPercent === null ? 'занятость неизвестна' : `занято ${String(disk.usedPercent).replace('.', ',')}%`,
      tone: disk.status === 'fail' ? 'critical' : disk.status === 'warning' ? 'warning' : 'ok',
      href: '/admin/system',
    },
  ]
}

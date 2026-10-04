const pad = (value: number): string => String(value).padStart(2, '0')

/** ISO-строка сервера -> значение для `<input type="datetime-local">` в часовом поясе браузера. */
export function toDateTimeLocal(iso: string | null): string {
  if (iso === null) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** Значение `datetime-local` (локальное время) -> ISO UTC для API; пустое значение -> null. */
export function fromDateTimeLocal(value: string): string | null {
  if (value === '') return null
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null

  return date.toISOString()
}

export function formatDateTime(iso: string | null): string {
  if (iso === null) return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return iso

  return date.toLocaleString('ru-RU', { dateStyle: 'medium', timeStyle: 'short' })
}

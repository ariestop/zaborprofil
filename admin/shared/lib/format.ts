export function formatDateTime(value: string | null): string {
  if (value === null) {
    return '—'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }

  return date.toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' })
}

export function formatNumber(value: number): string {
  return value.toLocaleString('ru-RU')
}

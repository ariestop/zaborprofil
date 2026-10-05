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

/** «4 октября 2026 г., 09:18» — для таблиц, где короткая дата 04.10.2026 читается хуже. */
export function formatDateTimeLong(value: string | null): string {
  if (value === null) {
    return '—'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }

  return date.toLocaleString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

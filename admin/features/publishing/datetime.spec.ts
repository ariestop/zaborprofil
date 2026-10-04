import { describe, expect, it } from 'vitest'
import { formatDateTime, fromDateTimeLocal, toDateTimeLocal } from './datetime'

describe('datetime helpers', () => {
  it('round-trips a local datetime through ISO', () => {
    const iso = fromDateTimeLocal('2026-11-05T14:30')

    expect(iso).toBe(new Date(2026, 10, 5, 14, 30).toISOString())
    expect(toDateTimeLocal(iso)).toBe('2026-11-05T14:30')
  })

  it('returns null or empty values for missing input', () => {
    expect(fromDateTimeLocal('')).toBeNull()
    expect(fromDateTimeLocal('not-a-date')).toBeNull()
    expect(toDateTimeLocal(null)).toBe('')
    expect(toDateTimeLocal('garbage')).toBe('')
    expect(formatDateTime(null)).toBe('—')
  })
})

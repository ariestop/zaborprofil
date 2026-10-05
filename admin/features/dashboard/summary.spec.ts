import { describe, expect, it } from 'vitest'
import { buildDailyBars, buildSiteState, countPublications, greeting, shortDate, todayLabel } from './summary'

describe('greeting', () => {
  it('follows the time of day', () => {
    expect(greeting(new Date(2026, 9, 4, 7))).toBe('Доброе утро')
    expect(greeting(new Date(2026, 9, 4, 13))).toBe('Добрый день')
    expect(greeting(new Date(2026, 9, 4, 19))).toBe('Добрый вечер')
    expect(greeting(new Date(2026, 9, 4, 2))).toBe('Доброй ночи')
  })

  it('writes the date with a capital weekday', () => {
    expect(todayLabel(new Date(2026, 9, 4))).toBe('Воскресенье, 4 октября')
  })
})

describe('buildDailyBars', () => {
  it('scales bars to the busiest day and marks the last one as today', () => {
    const bars = buildDailyBars(
      [
        { date: '2026-10-02', count: 0 },
        { date: '2026-10-03', count: 7 },
        { date: '2026-10-04', count: 3 },
      ],
      98,
    )

    expect(bars.map((bar) => bar.height)).toEqual([2, 98, 42])
    expect(bars.map((bar) => bar.today)).toEqual([false, false, true])
  })

  it('does not divide by zero when there are no leads', () => {
    expect(buildDailyBars([{ date: '2026-10-04', count: 0 }]).map((bar) => bar.height)).toEqual([2])
  })

  it('formats the start of the period', () => {
    expect(shortDate('2026-09-21')).toMatch(/^21 сент/)
  })
})

describe('countPublications', () => {
  it('counts published pages out of live ones', () => {
    expect(countPublications([{ status: 'published' }, { status: 'draft' }, { status: 'published' }, { status: 'deleted' }, { status: 'archived' }])).toEqual({
      published: 2,
      total: 3,
    })
  })
})

describe('buildSiteState', () => {
  it('describes each subsystem and skips the ones without data', () => {
    expect(buildSiteState({})).toEqual([])

    const rows = buildSiteState({ platformOk: true, phpVersion: '8.5', buildStatus: 'failed', hasBackups: false, disk: 'warning', failedMessages: 0 })

    expect(rows.map((row) => [row.id, row.value, row.tone])).toEqual([
      ['platform', 'OK · PHP 8.5', 'ok'],
      ['build', 'ошибка', 'critical'],
      ['backups', 'не найдены', 'critical'],
      ['disk', 'заканчивается', 'warning'],
      ['queue', '0 с ошибкой', 'ok'],
    ])
  })
})

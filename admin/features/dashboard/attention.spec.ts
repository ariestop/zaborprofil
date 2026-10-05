import { describe, expect, it } from 'vitest'
import { buildAttentionItems, formatWaiting } from './attention'

describe('buildAttentionItems', () => {
  it('returns nothing when there are no new leads, warnings or backup problems', () => {
    expect(buildAttentionItems({ newLeads: 0, warnings: [], hasBackups: true })).toEqual([])
  })

  it('puts new leads first with a Russian plural and a link to the filtered list', () => {
    const [first] = buildAttentionItems({ newLeads: 4, hasBackups: false })

    expect(first?.title).toBe('4 новые заявки без ответа')
    expect(first?.href).toBe('/admin/crm?status=new')
    expect(buildAttentionItems({ newLeads: 1 })[0]?.title).toBe('1 новая заявка без ответа')
    expect(buildAttentionItems({ newLeads: 11 })[0]?.title).toBe('11 новых заявок без ответа')
  })

  it('reports missing backups only when the backups state is known', () => {
    expect(buildAttentionItems({ hasBackups: false }).map((item) => item.id)).toEqual(['backups-missing'])
    expect(buildAttentionItems({ hasBackups: undefined })).toEqual([])
  })

  it('lists critical server warnings before ordinary ones', () => {
    const items = buildAttentionItems({
      warnings: [
        { code: 'disk', severity: 'warning', message: 'Disk usage is high.' },
        { code: 'db', severity: 'critical', message: 'Database is down.' },
      ],
    })

    expect(items.map((item) => item.id)).toEqual(['warning-db', 'warning-disk'])
    expect(items[0]?.tone).toBe('critical')
  })

  it('names disk warnings in plain words', () => {
    const items = buildAttentionItems({ warnings: [{ code: 'health_disk_warning', severity: 'warning', message: 'Диск: занято 97%.' }] })

    expect(items[0]?.title).toBe('Заканчивается место на диске')
    expect(items[0]?.description).toBe('Диск: занято 97%.')
  })

  it('flags failed queue messages and recent server errors only when data is known', () => {
    expect(buildAttentionItems({ failedMessages: 0, serverErrorsLastHour: 0 })).toEqual([])
    expect(buildAttentionItems({})).toEqual([])

    const items = buildAttentionItems({ failedMessages: 3, serverErrorsLastHour: 2 })

    expect(items.map((item) => [item.id, item.marker, item.tone])).toEqual([
      ['queue-failed', '3', 'critical'],
      ['server-errors', '2', 'warning'],
    ])
    expect(items[0]?.href).toBe('/admin/system/queues')
  })

  it('shows how long the oldest new lead has been waiting', () => {
    const [item] = buildAttentionItems({ newLeads: 2, oldestWaitMs: (3 * 60 + 12) * 60_000 })

    expect(item?.description).toBe('Самая ранняя ждёт 3 ч 12 мин')
    expect(item?.marker).toBe('2')
  })

  it('puts the failed frontend build right after leads and lets the user rebuild in place', () => {
    const items = buildAttentionItems({ newLeads: 1, hasBackups: false, buildFailed: true })

    expect(items.map((item) => item.id)).toEqual(['leads-new', 'build-failed', 'backups-missing'])
    expect(items[1]).toMatchObject({ tone: 'critical', action: 'rebuild', actionLabel: 'Пересобрать' })
    expect(buildAttentionItems({ buildFailed: false })).toEqual([])
  })
})

describe('formatWaiting', () => {
  it('formats minutes, hours and days', () => {
    expect(formatWaiting(20_000)).toBe('1 мин')
    expect(formatWaiting(45 * 60_000)).toBe('45 мин')
    expect(formatWaiting(60 * 60_000)).toBe('1 ч')
    expect(formatWaiting((3 * 60 + 12) * 60_000)).toBe('3 ч 12 мин')
    expect(formatWaiting((52) * 3_600_000)).toBe('2 д 4 ч')
    expect(formatWaiting(48 * 3_600_000)).toBe('2 д')
  })
})

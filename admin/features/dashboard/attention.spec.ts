import { describe, expect, it } from 'vitest'
import { buildAttentionItems } from './attention'

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
})

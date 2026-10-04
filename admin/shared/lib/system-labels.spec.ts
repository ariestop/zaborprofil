import { describe, expect, it } from 'vitest'
import { auditActionLabel, auditChanges, auditEntityLabel, formatBytes } from './system-labels'

describe('system labels', () => {
  it('translates audit actions and entity class names', () => {
    expect(auditActionLabel('update')).toBe('Изменение')
    expect(auditActionLabel('custom.action')).toBe('custom.action')
    expect(auditEntityLabel('App\\Module\\Content\\Domain\\Entity\\Page')).toBe('Страница')
    expect(auditEntityLabel('App\\Module\\Foo\\Bar')).toBe('Bar')
  })

  it('lists changed fields without the technical updatedAt', () => {
    expect(auditChanges(
      { ogType: null, updatedAt: '2026-10-04T14:29:57+03:00' },
      { ogType: 'website', updatedAt: '2026-10-04T14:29:58+03:00' },
    )).toEqual([{ field: 'ogType', before: '—', after: 'website' }])
  })

  it('formats file sizes in Russian units', () => {
    expect(formatBytes(512)).toBe('512 Б')
    expect(formatBytes(1536)).toBe('1,5 КБ')
  })
})

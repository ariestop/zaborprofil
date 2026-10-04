import { describe, expect, it } from 'vitest'
import { buildPaletteItems, filterPaletteItems } from './CommandPaletteDialog'

describe('command palette items', () => {
  const items = buildPaletteItems()

  it('contains quick actions and every navigable section without route params', () => {
    const paths = items.map((item) => item.path)

    expect(paths).toContain('/admin/pages/new')
    expect(paths).toContain('/admin/crm')
    expect(paths).toContain('/admin/system/backups')
    expect(paths.some((path) => path.includes('/:'))).toBe(false)
  })

  it('finds sections by title and by keywords, case-insensitively', () => {
    expect(filterPaletteItems(items, 'ЗАЯВ').map((item) => item.path)).toContain('/admin/crm')
    expect(filterPaletteItems(items, 'backup').map((item) => item.path)).toContain('/admin/system/backups')
    expect(filterPaletteItems(items, 'редирект').map((item) => item.title)).toContain('Добавить редирект')
  })

  it('returns everything for an empty query and nothing for nonsense', () => {
    expect(filterPaletteItems(items, '  ')).toHaveLength(items.length)
    expect(filterPaletteItems(items, 'qwertyuiop')).toHaveLength(0)
  })
})

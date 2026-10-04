import { describe, expect, it } from 'vitest'
import { resolveEditorTab } from './editor-tab'

describe('resolveEditorTab', () => {
  it('opens content for the base URL and legacy /builder route', () => {
    expect(resolveEditorTab(undefined)).toBe('content')
    expect(resolveEditorTab('builder')).toBe('content')
  })

  it('resolves known tabs and rejects unknown segments', () => {
    expect(resolveEditorTab('seo')).toBe('seo')
    expect(resolveEditorTab('settings')).toBe('settings')
    expect(resolveEditorTab('revisions')).toBe('revisions')
    expect(resolveEditorTab('unknown')).toBeNull()
  })
})

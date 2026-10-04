import { describe, expect, it } from 'vitest'
import { canPublishFrom, quickStatusActions, statusTone } from './page-status'

describe('page status helpers', () => {
  it('allows quick publish from draft, but not from published', () => {
    expect(canPublishFrom('draft')).toBe(true)
    expect(canPublishFrom('approved')).toBe(true)
    expect(canPublishFrom('published')).toBe(false)
    expect(canPublishFrom('review')).toBe(false)
  })

  it('leaves workflow transitions for the publishing slot', () => {
    expect(quickStatusActions('draft')).toEqual([])
    expect(quickStatusActions('published')).toEqual(['unpublished', 'archived'])
  })

  it('resolves badge tone', () => {
    expect(statusTone('published')).toBe('success')
    expect(statusTone('draft')).toBe('warning')
    expect(statusTone('archived')).toBe('neutral')
  })
})

import { describe, expect, it } from 'vitest'
import { resolveSaveState } from './save-state'

describe('resolveSaveState', () => {
  const base = { dirty: false, saving: false, failed: false, hasSavedOnce: false }

  it('is clean without changes and saved after a successful save', () => {
    expect(resolveSaveState(base)).toBe('clean')
    expect(resolveSaveState({ ...base, hasSavedOnce: true })).toBe('saved')
  })

  it('prioritizes saving, then error, then dirty', () => {
    expect(resolveSaveState({ ...base, dirty: true, failed: true, saving: true })).toBe('saving')
    expect(resolveSaveState({ ...base, dirty: true, failed: true })).toBe('error')
    expect(resolveSaveState({ ...base, dirty: true, hasSavedOnce: true })).toBe('dirty')
  })
})

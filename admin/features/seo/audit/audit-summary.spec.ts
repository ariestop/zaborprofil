import { describe, expect, it } from 'vitest'
import { countIssues, severityTone } from './audit-summary'

describe('audit summary', () => {
  it('counts issues per severity', () => {
    const counts = countIssues([
      { severity: 'P1', code: 'a', message: 'a', field: 'a' },
      { severity: 'P2', code: 'b', message: 'b', field: 'b' },
      { severity: 'P2', code: 'c', message: 'c', field: 'c' },
    ])

    expect(counts).toEqual({ P0: 0, P1: 1, P2: 2 })
  })

  it('highlights blocking severities', () => {
    expect(severityTone('P0')).toBe('warning')
    expect(severityTone('P1')).toBe('warning')
    expect(severityTone('P2')).toBe('neutral')
  })
})

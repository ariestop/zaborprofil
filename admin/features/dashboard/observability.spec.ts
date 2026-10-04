import { describe, expect, it } from 'vitest'
import type { SystemObservabilityResponse } from '../../types/api'
import { buildObservabilityTiles, formatBytes } from './observability'

function snapshot(overrides: Partial<SystemObservabilityResponse> = {}): SystemObservabilityResponse {
  return {
    serverErrors: { lastHour: 0, last24Hours: 0 },
    queue: { pending: 0, failed: 0 },
    disk: { status: 'ok', freeBytes: 5 * 1024 ** 3, totalBytes: 20 * 1024 ** 3, usedPercent: 75 },
    checkedAt: '2026-10-04T12:00:00+00:00',
    ...overrides,
  }
}

describe('formatBytes', () => {
  it('formats sizes in Russian units and handles unknown values', () => {
    expect(formatBytes(512)).toBe('512 Б')
    expect(formatBytes(1536)).toBe('1,5 КБ')
    expect(formatBytes(5 * 1024 ** 3)).toBe('5,0 ГБ')
    expect(formatBytes(120 * 1024 ** 2)).toBe('120 МБ')
    expect(formatBytes(null)).toBe('—')
  })
})

describe('buildObservabilityTiles', () => {
  it('is calm when nothing is wrong', () => {
    expect(buildObservabilityTiles(snapshot()).map((tile) => tile.tone)).toEqual(['ok', 'ok', 'ok'])
  })

  it('escalates recent server errors, failed queue messages and low disk space', () => {
    const tiles = buildObservabilityTiles(snapshot({
      serverErrors: { lastHour: 2, last24Hours: 7 },
      queue: { pending: 4, failed: 1 },
      disk: { status: 'fail', freeBytes: 300 * 1024 ** 2, totalBytes: 20 * 1024 ** 3, usedPercent: 97.4 },
    }))

    expect(tiles.map((tile) => [tile.id, tile.value, tile.tone])).toEqual([
      ['server-errors', '7', 'critical'],
      ['queue', '4', 'critical'],
      ['disk', '300 МБ', 'critical'],
    ])
    expect(tiles[0]?.hint).toBe('за последний час: 2')
    expect(tiles[1]?.hint).toBe('с ошибкой: 1')
    expect(tiles[2]?.hint).toBe('занято 97,4%')
  })

  it('treats older errors as a warning only', () => {
    const [errors] = buildObservabilityTiles(snapshot({ serverErrors: { lastHour: 0, last24Hours: 3 } }))

    expect(errors?.tone).toBe('warning')
    expect(errors?.hint).toBe('за последний час нет')
  })
})

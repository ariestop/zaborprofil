import { act, cleanup, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { usePageEditLock } from './usePageEditLock'

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const free = { locked: false, ownedByMe: true, holder: { label: 'me@example.test', isSelf: true, since: '2026-10-04T10:00:00+00:00', lastSeenAt: '2026-10-04T10:00:00+00:00' }, ttlSeconds: 90, heartbeatSeconds: 30 }
const busy = { ...free, locked: true, ownedByMe: false, holder: { ...free.holder, label: 'anna@example.test', isSelf: false } }

function lockCalls(method: string) {
  return apiRequest.mock.calls.filter(([url, options]) => String(url).endsWith('/edit-lock') && (options as { method: string }).method === method)
}

async function advance(ms: number): Promise<void> {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms)
  })
}

beforeEach(() => {
  vi.useFakeTimers()
  apiRequest.mockReset()
})

afterEach(() => {
  cleanup()
  vi.useRealTimers()
})

describe('usePageEditLock', () => {
  it('acquires the lock on mount and keeps it alive with heartbeats', async () => {
    apiRequest.mockResolvedValue(free)
    const { result } = renderHook(() => usePageEditLock('page-1'))

    await advance(0)
    expect(lockCalls('POST')).toHaveLength(1)
    expect(result.current.locked).toBe(false)

    await advance(30_000)
    await advance(30_000)
    expect(lockCalls('POST')).toHaveLength(3)
    const sessionIds = new Set(lockCalls('POST').map(([, options]) => (options as { body: { sessionId: string } }).body.sessionId))
    expect(sessionIds.size).toBe(1)
  })

  it('reports who is editing the page and lets the user take over', async () => {
    apiRequest.mockResolvedValue(busy)
    const { result } = renderHook(() => usePageEditLock('page-1'))
    await advance(0)

    expect(result.current.locked).toBe(true)
    expect(result.current.holderLabel).toBe('anna@example.test')
    expect(result.current.holderIsSelf).toBe(false)

    apiRequest.mockResolvedValue(free)
    await act(async () => {
      await result.current.takeOver()
    })

    expect((lockCalls('POST').at(-1)?.[1] as { body: { takeOver: boolean } }).body.takeOver).toBe(true)
    expect(result.current.locked).toBe(false)
  })

  it('releases the lock on unmount and ignores API failures', async () => {
    apiRequest.mockRejectedValue(new Error('offline'))
    const { result, unmount } = renderHook(() => usePageEditLock('page-1'))
    await advance(0)

    expect(result.current.locked).toBe(false)

    unmount()
    expect(lockCalls('DELETE')).toHaveLength(1)
  })
})

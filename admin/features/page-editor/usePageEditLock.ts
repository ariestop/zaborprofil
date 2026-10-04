import { useCallback, useEffect, useRef, useState } from 'react'
import { acquirePageEditLock, releasePageEditLock, type PageEditLockStatus } from '../../entities/page/api'

const DEFAULT_HEARTBEAT_MS = 30_000

export interface PageEditLockState {
  /** Страницу сейчас редактирует кто-то другой (или вы же в другой вкладке). */
  locked: boolean
  holderLabel: string | null
  holderIsSelf: boolean
  since: string | null
  takeOver: () => Promise<void>
}

function newSessionId(): string {
  return typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : `${Date.now().toString(16)}-${Math.random().toString(16).slice(2, 10)}`
}

/**
 * Мягкая блокировка «сейчас редактирует X»: heartbeat раз в 30 секунд, освобождение при закрытии.
 * Ошибки сети и прав игнорируются — блокировка только предупреждает и никогда не мешает работе.
 */
export function usePageEditLock(pageId: string): PageEditLockState {
  const [sessionId] = useState(newSessionId)
  const [status, setStatus] = useState<PageEditLockStatus | null>(null)
  const heartbeatMsRef = useRef(DEFAULT_HEARTBEAT_MS)

  const acquire = useCallback(async (takeOver: boolean): Promise<void> => {
    try {
      const next = await acquirePageEditLock(pageId, sessionId, takeOver)
      heartbeatMsRef.current = next.heartbeatSeconds > 0 ? next.heartbeatSeconds * 1000 : DEFAULT_HEARTBEAT_MS
      setStatus(next)
    } catch {
      setStatus(null)
    }
  }, [pageId, sessionId])

  useEffect(() => {
    let disposed = false
    let timer: number | undefined

    const tick = async () => {
      await acquire(false)
      if (!disposed) {
        timer = window.setTimeout(() => void tick(), heartbeatMsRef.current)
      }
    }

    void tick()

    const release = () => void releasePageEditLock(pageId, sessionId).catch(() => undefined)
    window.addEventListener('pagehide', release)

    return () => {
      disposed = true
      window.clearTimeout(timer)
      window.removeEventListener('pagehide', release)
      release()
    }
  }, [acquire, pageId, sessionId])

  const takeOver = useCallback(() => acquire(true), [acquire])

  return {
    locked: status?.locked === true,
    holderLabel: status?.holder?.label ?? null,
    holderIsSelf: status?.holder?.isSelf === true,
    since: status?.holder?.since ?? null,
    takeOver,
  }
}

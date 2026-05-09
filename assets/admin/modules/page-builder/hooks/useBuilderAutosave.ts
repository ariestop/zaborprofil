import { useEffect } from 'react'
import type { BuilderSnapshot } from '../types'

interface AutosaveOptions {
  pageId: string
  snapshot: BuilderSnapshot
  onAutosave: (pageId: string, snapshot: BuilderSnapshot) => Promise<void>
  enabled?: boolean
  intervalMs?: number
}

export function useBuilderAutosave({
  pageId,
  snapshot,
  onAutosave,
  enabled = true,
  intervalMs = 30_000,
}: AutosaveOptions) {
  useEffect(() => {
    if (!enabled) {
      return
    }

    const timer = window.setInterval(() => {
      void onAutosave(pageId, snapshot)
    }, intervalMs)

    return () => window.clearInterval(timer)
  }, [enabled, intervalMs, onAutosave, pageId, snapshot])
}

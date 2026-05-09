import { useEffect } from 'react'
import type { BuilderBlock } from '../types'

interface AutosaveOptions {
  pageId: string
  blocks: BuilderBlock[]
  onAutosave: (pageId: string, blocks: BuilderBlock[]) => Promise<void>
  enabled?: boolean
  intervalMs?: number
}

export function useBuilderAutosave({
  pageId,
  blocks,
  onAutosave,
  enabled = true,
  intervalMs = 30_000,
}: AutosaveOptions) {
  useEffect(() => {
    if (!enabled) {
      return
    }

    const timer = window.setInterval(() => {
      void onAutosave(pageId, blocks)
    }, intervalMs)

    return () => window.clearInterval(timer)
  }, [blocks, enabled, intervalMs, onAutosave, pageId])
}

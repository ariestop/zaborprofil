import { useMemo, useState } from 'react'
import type { BuilderSnapshot } from '../types'

const EMPTY_SNAPSHOT: BuilderSnapshot = { html: '', css: '' }

export function useBuilderDraft(initialSnapshot: BuilderSnapshot = EMPTY_SNAPSHOT) {
  const [snapshot, setSnapshot] = useState(initialSnapshot)
  const [previewMode, setPreviewMode] = useState(false)

  const isEmpty = useMemo(() => snapshot.html.trim() === '', [snapshot.html])

  return {
    snapshot,
    setSnapshot,
    previewMode,
    setPreviewMode,
    isEmpty,
  }
}

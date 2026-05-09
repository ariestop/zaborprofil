import { useMemo, useState } from 'react'
import type { BuilderBlock } from '../types'

export function useBuilderDraft(initialBlocks: BuilderBlock[] = []) {
  const [blocks, setBlocks] = useState<BuilderBlock[]>(initialBlocks)
  const [previewMode, setPreviewMode] = useState(false)

  const isEmpty = useMemo(() => blocks.length === 0, [blocks.length])

  return {
    blocks,
    setBlocks,
    previewMode,
    setPreviewMode,
    isEmpty,
  }
}

import { create } from 'zustand'
import type { BuilderBlock, BuilderValidationIssue } from '../types'

interface BuilderState {
  blocks: BuilderBlock[]
  selectedBlockId: string | null
  dirty: boolean
  validationIssues: BuilderValidationIssue[]
  setBlocks: (blocks: BuilderBlock[], markDirty?: boolean) => void
  selectBlock: (blockId: string | null) => void
  updateBlock: (blockId: string, updater: (block: BuilderBlock) => BuilderBlock) => void
  deleteBlock: (blockId: string) => void
  setDirty: (dirty: boolean) => void
  setValidationIssues: (issues: BuilderValidationIssue[]) => void
}

export const useBuilderStore = create<BuilderState>((set) => ({
  blocks: [],
  selectedBlockId: null,
  dirty: false,
  validationIssues: [],
  setBlocks: (blocks, markDirty = false) => {
    set({
      blocks,
      dirty: markDirty,
      selectedBlockId: blocks[0]?.id ?? null,
    })
  },
  selectBlock: (selectedBlockId) => set({ selectedBlockId }),
  updateBlock: (blockId, updater) => {
    set((state) => ({
      blocks: state.blocks.map((block) => (block.id === blockId ? updater(block) : block)),
      dirty: true,
    }))
  },
  deleteBlock: (blockId) => {
    set((state) => {
      const blocks = state.blocks.filter((block) => block.id !== blockId).map((block, index) => ({
        ...block,
        position: index,
      }))

      return {
        blocks,
        selectedBlockId: blocks[0]?.id ?? null,
        dirty: true,
      }
    })
  },
  setDirty: (dirty) => set({ dirty }),
  setValidationIssues: (validationIssues) => set({ validationIssues }),
}))

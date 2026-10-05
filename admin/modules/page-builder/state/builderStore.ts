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
    // Выделение сохраняется, если блок остался на странице (перестановка, вставка рядом).
    set((state) => ({
      blocks,
      dirty: markDirty,
      selectedBlockId: blocks.some((block) => block.id === state.selectedBlockId) ? state.selectedBlockId : blocks[0]?.id ?? null,
    }))
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
      const index = state.blocks.findIndex((block) => block.id === blockId)
      const blocks = state.blocks.filter((block) => block.id !== blockId).map((block, position) => ({
        ...block,
        position,
      }))
      // После удаления выделяется соседний блок, а не первый на странице.
      const neighbour = blocks[Math.min(Math.max(index, 0), blocks.length - 1)]

      return {
        blocks,
        selectedBlockId: state.selectedBlockId === blockId ? neighbour?.id ?? null : state.selectedBlockId,
        dirty: true,
      }
    })
  },
  setDirty: (dirty) => set({ dirty }),
  setValidationIssues: (validationIssues) => set({ validationIssues }),
}))

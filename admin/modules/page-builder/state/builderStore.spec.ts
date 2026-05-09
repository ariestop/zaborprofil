import { beforeEach, describe, expect, it } from 'vitest'
import { createBlock } from '../utils/pageBlocks'
import { useBuilderStore } from './builderStore'

describe('builder store dirty state', () => {
  beforeEach(() => {
    useBuilderStore.setState({
      blocks: [],
      selectedBlockId: null,
      dirty: false,
      validationIssues: [],
    })
  })

  it('marks dirty after block update', () => {
    const block = createBlock('cta', 0)
    useBuilderStore.getState().setBlocks([block])
    useBuilderStore.getState().updateBlock(block.id, (next) => ({ ...next, enabled: false }))

    expect(useBuilderStore.getState().dirty).toBe(true)
  })
})

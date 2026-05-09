import { describe, expect, it } from 'vitest'
import { findBuilderCanvasBlock } from './api'
import type { ContentBlockItem } from '../../types/api'

function createBlock(type: string, id: string): ContentBlockItem {
  return {
    id,
    pageId: 'page',
    type,
    name: type,
    position: 0,
    isEnabled: true,
    visibility: 'public',
    content: {},
    settings: {},
    createdAt: '2026-01-01T00:00:00+00:00',
    updatedAt: '2026-01-01T00:00:00+00:00',
  }
}

describe('findBuilderCanvasBlock', () => {
  it('returns builder canvas block when present', () => {
    const blocks = [
      createBlock('hero', '1'),
      createBlock('builder_canvas', '2'),
    ]

    expect(findBuilderCanvasBlock(blocks)?.id).toBe('2')
  })

  it('returns undefined when builder block missing', () => {
    const blocks = [createBlock('hero', '1')]
    expect(findBuilderCanvasBlock(blocks)).toBeUndefined()
  })
})

import { describe, expect, it } from 'vitest'
import { templateToBlocks } from './template-blocks'

describe('templateToBlocks', () => {
  it('creates independent blocks after the start position in template order', () => {
    const content = { items: [{ question: 'Q', answer: 'A' }] }
    const blocks = templateToBlocks({
      blocksSchema: [
        { type: 'faq', name: 'FAQ', position: 1, content, settings: {}, isEnabled: false },
        { type: 'hero.classic', name: 'Hero', position: 0, content: { title: 'Заголовок' }, settings: {}, isEnabled: true },
      ],
    }, 3)

    expect(blocks.map((block) => [block.type, block.position, block.enabled])).toEqual([
      ['hero.classic', 3, true],
      ['faq', 4, false],
    ])
    expect(new Set(blocks.map((block) => block.id)).size).toBe(2)
    expect(blocks[1]?.content).toEqual(content)
    expect(blocks[1]?.content).not.toBe(content)
  })
})

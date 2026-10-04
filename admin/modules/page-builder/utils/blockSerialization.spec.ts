import { describe, expect, it } from 'vitest'
import { createBlock, normalizePageBlocks, reorderBlocks, validatePageBlocks } from './pageBlocks'
import { deserializeBuilderBlock, deserializeBuilderBlocks, serializeBuilderBlocks, toJsonObject } from './blockSerialization'

const phpBlock = {
  id: '01JQY3Y5SFSVCE00H9GQWQY56V',
  type: 'hero',
  enabled: true,
  position: 0,
  content: { title: 'Hero' },
  settings: [],
  metadata: { createdAt: '2026-05-09T00:00:00+00:00', updatedAt: '2026-05-09T00:00:00+00:00' },
}

describe('toJsonObject', () => {
  it('turns PHP empty arrays and non-objects into an empty object', () => {
    expect(toJsonObject([])).toEqual({})
    expect(toJsonObject(null)).toEqual({})
    expect(toJsonObject(undefined)).toEqual({})
    expect(toJsonObject('text')).toEqual({})
    expect(toJsonObject(['a', 'b'])).toEqual({})
  })

  it('keeps non-empty objects untouched', () => {
    const value = { className: 'wide', nested: { a: 1 } }
    expect(toJsonObject(value)).toBe(value)
  })
})

describe('deserializeBuilderBlock', () => {
  it('normalizes legacy blocks created via content API (settings: [])', () => {
    const block = deserializeBuilderBlock(phpBlock, 0)

    expect(block.type).toBe('hero')
    expect(block.settings).toEqual({})
    expect(block.content).toEqual({ title: 'Hero' })
  })

  it('fills missing fields safely', () => {
    const block = deserializeBuilderBlock({ id: 'abc', type: 'text', content: [], settings: null }, 3)

    expect(block).toMatchObject({ id: 'abc', type: 'text', enabled: true, position: 3, content: {}, settings: {} })
    expect(typeof block.metadata.createdAt).toBe('string')
  })

  it('returns an empty list for non-array payloads', () => {
    expect(deserializeBuilderBlocks(undefined)).toEqual([])
    expect(deserializeBuilderBlocks({})).toEqual([])
  })
})

describe('legacy blocks in the page builder', () => {
  it('validates and normalizes legacy blocks returned by the backend', () => {
    const blocks = deserializeBuilderBlocks([
      phpBlock,
      { ...phpBlock, id: '01JQY3Y5SFSVCE00H9GQWQY57A', type: 'text', position: 1, content: { richText: '<p>Initial text</p>' } },
    ])

    const normalized = normalizePageBlocks(blocks)
    const validation = validatePageBlocks(normalized)

    expect(validation.issues).toEqual([])
    expect(validation.isValid).toBe(true)
    expect(normalized[1]?.content).toEqual({ richText: '<p>Initial text</p>' })
  })

  it('does not reject raw backend payload with settings: [] after normalization', () => {
    const raw = [{ ...phpBlock, type: 'rich-text', content: { html: '<p>x</p>' } }]
    const normalized = normalizePageBlocks(raw as never)

    expect(validatePageBlocks(normalized).isValid).toBe(true)
    expect(Array.isArray(normalized[0]?.settings)).toBe(false)
  })

  it('does not replace invalid content with defaults during normalization', () => {
    const block = { ...createBlock('hero.classic', 0), content: { subtitle: 'Без заголовка', title: 42 } }
    const [normalized] = normalizePageBlocks([block])

    expect(normalized?.content).toEqual({ subtitle: 'Без заголовка', title: 42 })
    expect(validatePageBlocks([block]).isValid).toBe(false)
  })

  it('reports unknown block types instead of crashing', () => {
    const block = deserializeBuilderBlock({ ...phpBlock, type: 'unknown-block' }, 0)
    const validation = validatePageBlocks(normalizePageBlocks([block]))

    expect(validation.isValid).toBe(false)
    expect(validation.issues[0]?.path).toBe('type')
  })
})

describe('serializeBuilderBlocks', () => {
  it('sends blocks in builder order with sequential positions and object settings', () => {
    const first = deserializeBuilderBlock(phpBlock, 0)
    const second = deserializeBuilderBlock({ ...phpBlock, id: 'second', type: 'text', position: 1 }, 1)
    const reordered = reorderBlocks([first, second], 1, 0)

    const payload = serializeBuilderBlocks(reordered)

    expect(payload.map((block) => block.type)).toEqual(['text', 'hero'])
    expect(payload.map((block) => block.position)).toEqual([0, 1])
    expect(JSON.stringify(payload)).not.toContain('"settings":[]')
    expect(JSON.stringify(payload)).toContain('"settings":{}')
  })
})

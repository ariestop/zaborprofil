import { describe, expect, it } from 'vitest'
import blockTypesConfig from '../../../../config/content/block-types.json'
import { blockModules } from '../blocks'
import { BLOCK_TYPES, LEGACY_BLOCK_TYPES, LEGACY_BLOCK_TYPE_ALIASES, STRUCTURED_BLOCK_TYPES, isLegacyBlockType } from '../types'
import { blockRegistry, blockRegistryByType, selectableBlockRegistry } from './blockRegistry'

describe('block type registry', () => {
  it('matches the shared config/content/block-types.json used by the backend', () => {
    expect([...STRUCTURED_BLOCK_TYPES]).toEqual(blockTypesConfig.structured)
    expect({ ...LEGACY_BLOCK_TYPE_ALIASES }).toEqual(blockTypesConfig.legacy)
    expect([...BLOCK_TYPES].sort()).toEqual([...blockTypesConfig.structured, ...Object.keys(blockTypesConfig.legacy)].sort())
  })

  it('has a registry definition and editor module for every backend block type', () => {
    for (const type of BLOCK_TYPES) {
      expect(blockRegistryByType.get(type), `definition for ${type}`).toBeDefined()
      expect(blockModules[type], `module for ${type}`).toBeDefined()
    }
    expect(blockRegistry).toHaveLength(BLOCK_TYPES.length)
  })

  it('keeps legacy types editable but hidden from the block catalog', () => {
    expect(isLegacyBlockType('hero')).toBe(true)
    expect(isLegacyBlockType('hero.classic')).toBe(false)

    const selectableTypes = selectableBlockRegistry.map((definition) => definition.type)
    for (const type of LEGACY_BLOCK_TYPES) {
      expect(selectableTypes).not.toContain(type)
      expect(blockRegistryByType.get(type)?.legacy).toBe(true)
    }
    expect(selectableTypes).toHaveLength(STRUCTURED_BLOCK_TYPES.length)
  })

  it('maps legacy types to structured canonical types', () => {
    expect(blockRegistryByType.get('hero')?.canonicalType).toBe('hero.classic')
    expect(blockRegistryByType.get('text')?.canonicalType).toBe('rich-text')
    expect(blockRegistryByType.get('html_embed')?.canonicalType).toBeUndefined()
  })

  it('legacy schemas keep unknown fields', () => {
    const definition = blockRegistryByType.get('text')
    const result = definition?.contentSchema.safeParse({ richText: '<p>x</p>', extra: 1 })

    expect(result?.success).toBe(true)
    expect(result?.success && result.data).toEqual({ richText: '<p>x</p>', extra: 1 })
  })
})

import type { BuilderBlockType } from '../../types'
import { blockRegistryByType } from '../../registry/blockRegistry'

export function createDefaults(type: BuilderBlockType) {
  const definition = blockRegistryByType.get(type)
  if (definition === undefined) {
    throw new Error(`Unknown block type: ${type}`)
  }

  return {
    content: structuredClone(definition.defaults.content),
    settings: structuredClone(definition.defaults.settings),
  }
}

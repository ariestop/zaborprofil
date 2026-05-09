import { z } from 'zod'
import { blockRegistry } from '../registry/blockRegistry'
import type { BuilderBlockType } from '../types'
import { GenericBlockEditor } from './shared/GenericBlockEditor'
import { GenericBlockPreview } from './shared/GenericBlockPreview'

export interface BlockModuleDefinition {
  schema: z.ZodType<Record<string, unknown>>
  defaults: {
    content: Record<string, unknown>
    settings: Record<string, unknown>
  }
  Editor: typeof GenericBlockEditor
  Preview: typeof GenericBlockPreview
}

export const blockModules: Record<BuilderBlockType, BlockModuleDefinition> = blockRegistry.reduce((accumulator, definition) => {
  accumulator[definition.type] = {
    schema: definition.contentSchema as z.ZodType<Record<string, unknown>>,
    defaults: definition.defaults,
    Editor: GenericBlockEditor,
    Preview: GenericBlockPreview,
  }
  return accumulator
}, {} as Record<BuilderBlockType, BlockModuleDefinition>)

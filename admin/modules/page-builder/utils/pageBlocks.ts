import type { BuilderBlock, BuilderBlockType, BuilderValidationIssue, BuilderValidationResult } from '../types'
import { builderBlockSchema } from '../types'
import { blockRegistryByType } from '../registry/blockRegistry'

function nowIso() {
  return new Date().toISOString()
}

export function createBlock(type: BuilderBlockType, position: number): BuilderBlock {
  const definition = blockRegistryByType.get(type)
  if (definition === undefined) {
    throw new Error(`Unsupported block type: ${type}`)
  }

  const timestamp = nowIso()
  return {
    id: crypto.randomUUID(),
    type,
    enabled: true,
    position,
    content: structuredClone(definition.defaults.content),
    settings: structuredClone(definition.defaults.settings),
    metadata: {
      createdAt: timestamp,
      updatedAt: timestamp,
    },
  }
}

export function duplicateBlock(block: BuilderBlock, position: number): BuilderBlock {
  const timestamp = nowIso()
  return {
    ...block,
    id: crypto.randomUUID(),
    position,
    metadata: {
      createdAt: timestamp,
      updatedAt: timestamp,
    },
    content: structuredClone(block.content),
    settings: structuredClone(block.settings),
  }
}

export function reorderBlocks(blocks: BuilderBlock[], sourceIndex: number, targetIndex: number): BuilderBlock[] {
  const reordered = [...blocks]
  const [moved] = reordered.splice(sourceIndex, 1)
  if (moved === undefined) {
    return blocks
  }

  reordered.splice(targetIndex, 0, moved)

  return reordered.map((block, index) => ({
    ...block,
    position: index,
    metadata: {
      ...block.metadata,
      updatedAt: nowIso(),
    },
  }))
}

export function validatePageBlocks(blocks: BuilderBlock[]): BuilderValidationResult {
  const issues: BuilderValidationIssue[] = []

  blocks.forEach((block) => {
    const parseBlock = builderBlockSchema.safeParse(block)
    if (!parseBlock.success) {
      parseBlock.error.issues.forEach((issue) => {
        issues.push({
          blockId: block.id,
          path: issue.path.join('.'),
          message: issue.message,
        })
      })
      return
    }

    const definition = blockRegistryByType.get(block.type)
    if (definition === undefined) {
      issues.push({
        blockId: block.id,
        path: 'type',
        message: `Неизвестный тип блока: ${block.type}`,
      })
      return
    }

    const contentResult = definition.contentSchema.safeParse(block.content)
    if (!contentResult.success) {
      contentResult.error.issues.forEach((issue) => {
        issues.push({
          blockId: block.id,
          path: `content.${issue.path.join('.')}`,
          message: issue.message,
        })
      })
    }

    const settingsResult = definition.settingsSchema.safeParse(block.settings)
    if (!settingsResult.success) {
      settingsResult.error.issues.forEach((issue) => {
        issues.push({
          blockId: block.id,
          path: `settings.${issue.path.join('.')}`,
          message: issue.message,
        })
      })
    }
  })

  return {
    isValid: issues.length === 0,
    issues,
  }
}

export function normalizePageBlocks(blocks: BuilderBlock[]): BuilderBlock[] {
  return blocks
    .slice()
    .sort((left, right) => left.position - right.position)
    .map((block, index) => {
      const definition = blockRegistryByType.get(block.type)
      if (definition === undefined) {
        return {
          ...block,
          position: index,
        }
      }

      const normalizedContent = definition.contentSchema.safeParse(block.content)
      const normalizedSettings = definition.settingsSchema.safeParse(block.settings)

      return {
        ...block,
        position: index,
        content: normalizedContent.success ? normalizedContent.data : structuredClone(definition.defaults.content),
        settings: normalizedSettings.success ? normalizedSettings.data : structuredClone(definition.defaults.settings),
      }
    })
}

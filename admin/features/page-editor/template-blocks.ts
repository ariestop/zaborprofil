import type { BuilderBlock } from '../../modules/page-builder/types'
import type { PageTemplateItem } from '../../types/api'

/** Превращает блоки шаблона в новые блоки Page Builder с отдельными id, добавляемые после `startPosition`. */
export function templateToBlocks(template: Pick<PageTemplateItem, 'blocksSchema'>, startPosition: number): BuilderBlock[] {
  const timestamp = new Date().toISOString()

  return [...template.blocksSchema]
    .sort((left, right) => left.position - right.position)
    .map((block, index) => ({
      id: crypto.randomUUID(),
      type: block.type as BuilderBlock['type'],
      enabled: block.isEnabled,
      position: startPosition + index,
      content: structuredClone(block.content),
      settings: structuredClone(block.settings),
      metadata: { createdAt: timestamp, updatedAt: timestamp },
    }))
}

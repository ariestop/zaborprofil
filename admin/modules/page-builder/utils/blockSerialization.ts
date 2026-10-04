import type { BuilderBlock, BuilderBlockType } from '../types'

/**
 * PHP сериализует пустой ассоциативный массив как `[]`, а builder ждёт объект.
 * На границе API любое не-объектное значение приводится к `{}`, непустые объекты сохраняются как есть.
 */
export function toJsonObject(value: unknown): Record<string, unknown> {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return {}
  }

  return value as Record<string, unknown>
}

function toIsoString(value: unknown, fallback: string): string {
  return typeof value === 'string' && value !== '' ? value : fallback
}

export function deserializeBuilderBlock(raw: unknown, index: number): BuilderBlock {
  const source = toJsonObject(raw)
  const now = new Date().toISOString()
  const metadata = toJsonObject(source.metadata)
  const position = typeof source.position === 'number' && Number.isInteger(source.position) ? source.position : index

  return {
    id: typeof source.id === 'string' ? source.id : '',
    type: (typeof source.type === 'string' ? source.type : '') as BuilderBlockType,
    enabled: typeof source.enabled === 'boolean' ? source.enabled : true,
    position,
    content: toJsonObject(source.content),
    settings: toJsonObject(source.settings),
    metadata: {
      createdAt: toIsoString(metadata.createdAt, now),
      updatedAt: toIsoString(metadata.updatedAt, now),
    },
  }
}

export function deserializeBuilderBlocks(raw: unknown): BuilderBlock[] {
  if (!Array.isArray(raw)) {
    return []
  }

  return raw.map((block, index) => deserializeBuilderBlock(block, index))
}

/** Порядок блоков в массиве — источник правды: position пересчитывается при отправке. */
export function serializeBuilderBlocks(blocks: BuilderBlock[]): BuilderBlock[] {
  return blocks.map((block, index) => ({
    ...block,
    position: index,
    content: toJsonObject(block.content),
    settings: toJsonObject(block.settings),
  }))
}

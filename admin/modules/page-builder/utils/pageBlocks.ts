import type { BuilderBlock, BuilderBlockType, BuilderValidationIssue, BuilderValidationResult } from '../types'
import { builderBlockSchema } from '../types'
import { blockRegistryByType } from '../registry/blockRegistry'
import { toJsonObject } from './blockSerialization'

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

const REQUIRED_TEXT: Partial<Record<BuilderBlockType, Array<{ key: string, message: string }>>> = {
  'hero.classic': [{ key: 'title', message: 'Укажите заголовок первого экрана.' }],
  'rich-text': [{ key: 'html', message: 'Текст не может быть пустым.' }],
  cta: [{ key: 'title', message: 'Укажите заголовок призыва.' }],
  'contact-form': [{ key: 'title', message: 'Укажите заголовок формы.' }],
}

function isBlankText(value: unknown): boolean {
  if (typeof value !== 'string') {
    return true
  }

  return value.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim() === '' && !/<img\b/i.test(value)
}

/** Поля, без которых сервер не примет блок (StructuredBlockPayloadValidator): проверяем заранее, чтобы автосохранение не падало с 422. */
export function requiredContentIssues(block: BuilderBlock): BuilderValidationIssue[] {
  const issues: BuilderValidationIssue[] = []
  for (const rule of REQUIRED_TEXT[block.type] ?? []) {
    if (isBlankText(block.content[rule.key])) {
      issues.push({ blockId: block.id, path: `content.${rule.key}`, message: rule.message })
    }
  }

  if (block.type === 'faq' && Array.isArray(block.content.items)) {
    block.content.items.forEach((item, index) => {
      const record = (typeof item === 'object' && item !== null ? item : {}) as Record<string, unknown>
      if (isBlankText(record.question)) {
        issues.push({ blockId: block.id, path: `content.items.${index}.question`, message: `Вопрос ${index + 1}: укажите текст вопроса.` })
      }
      if (isBlankText(record.answer)) {
        issues.push({ blockId: block.id, path: `content.items.${index}.answer`, message: `Вопрос ${index + 1}: укажите ответ.` })
      }
    })
  }

  return issues
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

    for (const issue of requiredContentIssues(block)) {
      issues.push(issue)
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
      const content = toJsonObject(block.content)
      const settings = toJsonObject(block.settings)
      const definition = blockRegistryByType.get(block.type)
      if (definition === undefined) {
        return {
          ...block,
          position: index,
          content,
          settings,
        }
      }

      const normalizedContent = definition.contentSchema.safeParse(content)
      const normalizedSettings = definition.settingsSchema.safeParse(settings)

      // Данные, не прошедшие схему, не заменяются дефолтами: иначе автосохранение молча затёрло бы контент.
      // Проблему покажет validatePageBlocks.
      return {
        ...block,
        position: index,
        content: normalizedContent.success ? normalizedContent.data : content,
        settings: normalizedSettings.success ? normalizedSettings.data : settings,
      }
    })
}

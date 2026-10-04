import type { BuilderBlock } from '../../modules/page-builder/types'
import type { PageEditorFormValues } from './form'

const DRAFT_VERSION = 1
const DRAFT_PREFIX = 'zaborprofil:admin:page-draft:'

export interface PageDraft {
  version: typeof DRAFT_VERSION
  savedAt: string
  /** Версия блоков на сервере, от которой начаты правки; при расхождении восстановление приведёт к диалогу конфликта. */
  baseVersion: string | null
  blocks: BuilderBlock[]
  values: PageEditorFormValues
}

function storage(): Storage | null {
  try {
    const candidate = window.localStorage
    return typeof candidate?.getItem === 'function' && typeof candidate.setItem === 'function' && typeof candidate.removeItem === 'function'
      ? candidate
      : null
  } catch {
    return null
  }
}

export function draftKey(pageId: string): string {
  return `${DRAFT_PREFIX}${pageId}`
}

export function readDraft(pageId: string): PageDraft | null {
  try {
    const raw = storage()?.getItem(draftKey(pageId))
    if (raw === null || raw === undefined) {
      return null
    }

    const parsed = JSON.parse(raw) as Partial<PageDraft> | null
    if (
      parsed === null
      || typeof parsed !== 'object'
      || parsed.version !== DRAFT_VERSION
      || typeof parsed.savedAt !== 'string'
      || !Array.isArray(parsed.blocks)
      || typeof parsed.values !== 'object'
      || parsed.values === null
    ) {
      return null
    }

    return {
      version: DRAFT_VERSION,
      savedAt: parsed.savedAt,
      baseVersion: typeof parsed.baseVersion === 'string' ? parsed.baseVersion : null,
      blocks: parsed.blocks,
      values: parsed.values,
    }
  } catch {
    return null
  }
}

export function writeDraft(pageId: string, snapshot: Pick<PageDraft, 'baseVersion' | 'blocks' | 'values'>): void {
  try {
    const draft: PageDraft = { version: DRAFT_VERSION, savedAt: new Date().toISOString(), ...snapshot }
    storage()?.setItem(draftKey(pageId), JSON.stringify(draft))
  } catch {
    // Переполненное или недоступное хранилище не должно ломать редактор: черновик — лишь страховка.
  }
}

export function clearDraft(pageId: string): void {
  try {
    storage()?.removeItem(draftKey(pageId))
  } catch {
    // см. writeDraft
  }
}

function stableStringify(value: unknown): string {
  return JSON.stringify(value, (_key, item: unknown) => {
    if (item !== null && typeof item === 'object' && !Array.isArray(item)) {
      return Object.fromEntries(Object.entries(item as Record<string, unknown>).sort(([a], [b]) => a.localeCompare(b)))
    }

    return item
  })
}

/** Черновик нужен, только если он отличается от состояния, загруженного с сервера. */
export function draftDiffersFromServer(
  draft: PageDraft,
  server: { blocks: BuilderBlock[], values: PageEditorFormValues },
): boolean {
  return stableStringify(draft.blocks) !== stableStringify(server.blocks)
    || stableStringify(draft.values) !== stableStringify(server.values)
}

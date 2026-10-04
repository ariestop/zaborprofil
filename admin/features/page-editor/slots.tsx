import type { ReactNode } from 'react'
import type { PageStatus } from '../../entities/page/api'
import type { SaveState } from './save-state'

/**
 * Точки расширения единого редактора страницы.
 *
 * Workflow публикации (UI статусов, диффов ревизий) живёт в `admin/features/publishing/`
 * и подключается здесь, не затрагивая остальной код редактора.
 */

export interface PagePublishingSlotProps {
  pageId: string
  status: PageStatus
  saveState: SaveState
  hasUnsavedChanges: boolean
  /** Сохраняет все несохранённые изменения; resolve(true), если сохранение прошло успешно. */
  saveAll: () => Promise<boolean>
}

/** Панель статуса и публикации в шапке редактора (рядом с быстрой публикацией). */
export function PagePublishingSlot(props: PagePublishingSlotProps): ReactNode {
  void props
  return null
}

export interface PageRevisionsSlotProps {
  pageId: string
  status: PageStatus
}

/** Дополнительные блоки во вкладке «Ревизии» (например, сравнение версий). */
export function PageRevisionsSlot(props: PageRevisionsSlotProps): ReactNode {
  void props
  return null
}

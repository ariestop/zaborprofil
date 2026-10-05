export type SaveState = 'clean' | 'dirty' | 'saving' | 'saved' | 'error'

export interface SaveStateInput {
  dirty: boolean
  saving: boolean
  failed: boolean
  hasSavedOnce: boolean
}

export function resolveSaveState({ dirty, saving, failed, hasSavedOnce }: SaveStateInput): SaveState {
  if (saving) {
    return 'saving'
  }

  if (failed) {
    return 'error'
  }

  if (dirty) {
    return 'dirty'
  }

  return hasSavedOnce ? 'saved' : 'clean'
}

export const saveStateLabels: Record<SaveState, string> = {
  clean: 'Изменений нет',
  dirty: 'Есть несохранённые изменения',
  saving: 'Сохранение…',
  saved: 'Все изменения сохранены',
  error: 'Не удалось сохранить',
}

export function formatSavedAt(date: Date): string {
  return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

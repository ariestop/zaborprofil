import { cn } from '../../shared/lib/cn'
import { formatSavedAt, saveStateLabels, type SaveState } from './save-state'

interface SaveIndicatorProps {
  state: SaveState
  lastSavedAt: Date | null
  autosaveActive: boolean
  hint?: string
}

const dotClasses: Record<SaveState, string> = {
  clean: 'bg-slate-300 dark:bg-slate-600',
  dirty: 'bg-amber-500',
  saving: 'bg-sky-500 animate-pulse',
  saved: 'bg-emerald-500',
  error: 'bg-red-500',
}

export function SaveIndicator({ state, lastSavedAt, autosaveActive, hint }: SaveIndicatorProps) {
  const savedAt = state === 'saved' && lastSavedAt !== null ? ` в ${formatSavedAt(lastSavedAt)}` : ''

  return (
    <div className="flex flex-col text-sm" role="status" aria-live="polite" data-testid="save-indicator" data-state={state}>
      <span className="inline-flex items-center gap-2 font-medium text-slate-700 dark:text-slate-200">
        <span className={cn('inline-block h-2.5 w-2.5 rounded-full', dotClasses[state])} aria-hidden="true" />
        {saveStateLabels[state]}{savedAt}
      </span>
      <span className="text-xs text-slate-500 dark:text-slate-400">
        {hint ?? (autosaveActive ? 'Автосохранение включено для SEO и блоков' : 'Автосохранение выключено для опубликованной страницы')}
      </span>
    </div>
  )
}

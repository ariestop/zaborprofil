import { cn } from '../../shared/lib/cn'
import { formatSavedAt, saveStateLabels, type SaveState } from './save-state'

interface SaveIndicatorProps {
    state: SaveState
    lastSavedAt: Date | null
    autosaveActive: boolean
    hint?: string
}

const dotClasses: Record<SaveState, string> = {
    clean: 'bg-line-strong dark:bg-slate-600',
    dirty: 'bg-amber-500',
    saving: 'bg-sky-500 animate-pulse',
    saved: 'bg-brand-500',
    error: 'bg-red-500',
}

export function SaveIndicator({ state, lastSavedAt, autosaveActive, hint }: SaveIndicatorProps) {
    const savedAt =
        state === 'saved' && lastSavedAt !== null ? ` в ${formatSavedAt(lastSavedAt)}` : ''

    return (
        <div
            className="flex flex-col text-sm"
            role="status"
            aria-live="polite"
            data-testid="save-indicator"
            data-state={state}
        >
            <span className="inline-flex items-center gap-2 font-medium text-ink dark:text-slate-200">
                <span
                    className={cn('inline-block h-2.5 w-2.5 rounded-full', dotClasses[state])}
                    aria-hidden="true"
                />
                {saveStateLabels[state]}
                {savedAt}
            </span>
            <span className="text-xs text-graphite dark:text-slate-400">
                {hint ??
                    (autosaveActive
                        ? 'Автосохранение включено для SEO и блоков'
                        : 'Автосохранение выключено для опубликованной страницы')}
            </span>
        </div>
    )
}

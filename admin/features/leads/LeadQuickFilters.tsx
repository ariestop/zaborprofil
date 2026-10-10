import { useState } from 'react'
import { NavIcon } from '../../layouts/nav-icons'
import { cn } from '../../shared/lib/cn'
import type { LeadSavedView, useLeadSavedViews } from './saved-views'

const chipBase =
    'h-[30px] rounded-full border px-2.5 text-xs transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500'
const chipOn =
    'border-brand-200 bg-brand-50 font-semibold text-brand-800 dark:border-brand-800 dark:bg-brand-900/30 dark:text-brand-200'
const chipOff =
    'border-line-strong bg-white font-medium text-graphite hover:bg-surface dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'

/**
 * Быстрые фильтры CRM («Сегодня», «Юрлица B2B», «Без ответа») и сохранённые виды: применить, удалить,
 * сохранить текущие фильтры под именем.
 */
export function LeadQuickFilters({
    todayActive,
    b2b,
    waiting,
    waitingHours,
    savedViews,
    onToggleToday,
    onToggleB2b,
    onToggleWaiting,
    onApplyView,
    onSaveView,
}: {
    todayActive: boolean
    b2b: boolean
    waiting: boolean
    /** Порог фильтра «Без ответа», часов. */
    waitingHours: number
    savedViews: ReturnType<typeof useLeadSavedViews>
    onToggleToday: () => void
    onToggleB2b: () => void
    onToggleWaiting: () => void
    onApplyView: (view: LeadSavedView) => void
    onSaveView: (name: string) => void
}) {
    const [savingView, setSavingView] = useState(false)
    const [viewName, setViewName] = useState('')

    const save = () => {
        const name = viewName.trim()
        if (name === '') {
            return
        }
        onSaveView(name)
        setSavingView(false)
        setViewName('')
    }

    return (
        <>
            <div className="flex flex-wrap gap-1.5">
                <button
                    type="button"
                    aria-pressed={todayActive}
                    onClick={onToggleToday}
                    className={cn(chipBase, todayActive ? chipOn : chipOff)}
                >
                    Сегодня
                </button>
                <button
                    type="button"
                    aria-pressed={b2b}
                    onClick={onToggleB2b}
                    className={cn(chipBase, b2b ? chipOn : chipOff)}
                >
                    Юрлица B2B
                </button>
                <button
                    type="button"
                    aria-pressed={waiting}
                    onClick={onToggleWaiting}
                    className={cn(chipBase, waiting ? chipOn : chipOff)}
                >
                    Без ответа больше {waitingHours} ч
                </button>
                {savedViews.views.map((view) => (
                    <span
                        key={view.id}
                        className={cn(chipBase, chipOff, 'inline-flex items-center gap-1 pr-1')}
                    >
                        <button
                            type="button"
                            onClick={() => onApplyView(view)}
                            className="max-w-36 truncate focus-visible:outline-hidden"
                        >
                            {view.name}
                        </button>
                        <button
                            type="button"
                            aria-label={`Удалить вид «${view.name}»`}
                            onClick={() => savedViews.remove(view.id)}
                            className="flex h-5 w-5 items-center justify-center rounded-full text-graphite hover:bg-surface-strong dark:hover:bg-slate-800"
                        >
                            <NavIcon name="close" size={12} />
                        </button>
                    </span>
                ))}
                {savedViews.views.length < savedViews.limit ? (
                    <button
                        type="button"
                        onClick={() => setSavingView((value) => !value)}
                        className={cn(
                            chipBase,
                            'border-dashed border-line-strong bg-white font-medium text-graphite hover:bg-surface dark:hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400',
                        )}
                    >
                        + Сохранить вид
                    </button>
                ) : null}
            </div>

            {savingView ? (
                <form
                    className="flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault()
                        save()
                    }}
                >
                    <input
                        aria-label="Название вида"
                        placeholder="Название, например «B2B без ответа»"
                        maxLength={40}
                        autoFocus
                        value={viewName}
                        onChange={(event) => setViewName(event.target.value)}
                        className="h-12 min-w-0 flex-1 rounded-lg border border-line-strong bg-white px-2.5 text-[13px] dark:border-slate-700 dark:bg-slate-900"
                    />
                    <button
                        type="submit"
                        disabled={viewName.trim() === ''}
                        className="h-12 rounded-lg bg-brand-700 px-3 text-[13px] font-semibold text-white disabled:opacity-60"
                    >
                        Сохранить
                    </button>
                </form>
            ) : null}
        </>
    )
}

import type { LeadListResponse } from '../../entities/lead/model'
import { cn } from '../../shared/lib/cn'
import { formatNumber } from '../../shared/lib/format'
import type { LeadStatus } from '../../types/api'

const TABS: Array<{ id: LeadStatus | 'all'; label: string }> = [
    { id: 'all', label: 'Все' },
    { id: 'new', label: 'Новые' },
    { id: 'in_progress', label: 'В работе' },
    { id: 'done', label: 'Готово' },
    { id: 'spam', label: 'Спам' },
]

/** Вкладки статусов со счётчиками. «Все» не включает спам. */
export function LeadStatusTabs({
    status,
    counts,
    onChange,
}: {
    status: LeadStatus | 'all'
    counts: LeadListResponse['counts'] | undefined
    onChange: (status: LeadStatus | 'all') => void
}) {
    const tabCount = (id: LeadStatus | 'all'): string => {
        if (counts === undefined) {
            return ''
        }

        return formatNumber(
            id === 'all' ? counts.total - (counts.byStatus.spam ?? 0) : (counts.byStatus[id] ?? 0),
        )
    }

    return (
        <div
            role="tablist"
            aria-label="Статус заявок"
            className="flex gap-0.5 rounded-[11px] bg-line p-[3px] dark:bg-slate-800"
        >
            {TABS.map((tab) => {
                const active = status === tab.id

                return (
                    <button
                        key={tab.id}
                        type="button"
                        role="tab"
                        aria-selected={active}
                        onClick={() => onChange(tab.id)}
                        className={cn(
                            'flex h-9 flex-[1_1_auto] items-center justify-center gap-1 whitespace-nowrap rounded-[9px] px-1.5 text-xs font-semibold focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500',
                            active
                                ? 'bg-white text-ink dark:bg-slate-950 dark:text-slate-100'
                                : 'bg-transparent text-graphite dark:text-slate-400',
                        )}
                    >
                        {tab.label}
                        <span className="text-[11px] font-bold text-graphite dark:text-slate-400">
                            {tabCount(tab.id)}
                        </span>
                    </button>
                )
            })}
        </div>
    )
}

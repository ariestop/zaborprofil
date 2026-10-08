import { Link } from 'react-router-dom'
import { leadSourceLabel } from '../../entities/lead/model'
import { formatLeadListTime, leadInitials } from '../../entities/lead/presentation'
import { cn } from '../../shared/lib/cn'
import type { LeadItem } from '../../types/api'

/** Строка списка заявок: инициалы, имя, отметка «не прочитана», время, начало сообщения, источник, B2B. */
export function LeadListRow({
    lead,
    href,
    isCurrent,
}: {
    lead: LeadItem
    href: string
    isCurrent: boolean
}) {
    const unread = lead.readAt === null && lead.status === 'new'

    return (
        <li data-testid="lead-row">
            <Link
                to={href}
                aria-current={isCurrent ? 'true' : undefined}
                className={cn(
                    'flex w-full gap-3 rounded-xl border px-3 py-[13px] text-left text-sm text-ink no-underline hover:text-ink focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 dark:text-slate-100',
                    isCurrent
                        ? 'border-brand-200 bg-brand-50 dark:border-brand-800 dark:bg-brand-900/20'
                        : 'border-transparent bg-transparent hover:bg-surface dark:hover:bg-slate-800/60',
                )}
            >
                <span
                    aria-hidden="true"
                    className="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-full bg-surface-strong text-[13px] font-bold text-graphite dark:bg-slate-800 dark:text-slate-200"
                >
                    {leadInitials(lead.name)}
                </span>
                <span className="flex min-w-0 flex-1 flex-col gap-[3px]">
                    <span className="flex items-center gap-2">
                        <span
                            className={cn(
                                'min-w-0 flex-1 truncate',
                                unread ? 'font-bold' : 'font-medium',
                            )}
                        >
                            {lead.name}
                        </span>
                        {unread ? (
                            <span
                                aria-label="Не прочитана"
                                className="h-2 w-2 shrink-0 rounded-full bg-orange-700"
                            />
                        ) : null}
                        <time
                            dateTime={lead.createdAt}
                            className="shrink-0 text-xs text-graphite dark:text-slate-400"
                        >
                            {formatLeadListTime(lead.createdAt)}
                        </time>
                    </span>
                    {lead.messagePreview !== null ? (
                        <span className="line-clamp-2 text-[13px] text-graphite dark:text-slate-300">
                            {lead.messagePreview}
                        </span>
                    ) : null}
                    <span className="flex flex-wrap gap-1.5 text-xs text-graphite dark:text-slate-400">
                        <span>{leadSourceLabel(lead.source)}</span>
                        {lead.b2b ? (
                            <span className="rounded-[5px] bg-indigo-100 px-1.5 font-bold text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                B2B
                            </span>
                        ) : null}
                    </span>
                </span>
            </Link>
        </li>
    )
}

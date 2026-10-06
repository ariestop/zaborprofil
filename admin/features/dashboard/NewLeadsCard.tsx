import { Link } from 'react-router-dom'
import { leadSourceLabel } from '../../entities/lead/model'
import { formatLeadListTime, leadInitials, leadPagePath } from '../../entities/lead/presentation'
import { NavIcon } from '../../layouts/nav-icons'
import { cn } from '../../shared/lib/cn'
import { Skeleton } from '../../shared/ui'
import type { LeadItem } from '../../types/api'
import { dashCard, dashHeading, dashMuted, dashOutlineButton } from './styles'

/** Откуда пришла заявка: страница сайта (`/` — «главная»), иначе название источника. */
export function leadOrigin(lead: Pick<LeadItem, 'pageUrl' | 'source'>): string {
    if (lead.pageUrl === null || lead.pageUrl.trim() === '') {
        return leadSourceLabel(lead.source)
    }
    const path = leadPagePath(lead.pageUrl)

    return path === '/' ? 'главная' : path
}

export function telHref(phone: string): string {
    return `tel:${phone.replace(/[^\d+]/g, '')}`
}

interface NewLeadsCardProps {
    leads: LeadItem[] | undefined
    loading: boolean
    error: boolean
    canManage: boolean
    busyLeadId: string | null
    onTake: (lead: LeadItem) => void
}

/** «Новые заявки»: самые свежие неразобранные, с быстрыми действиями «Позвонить» и «В работу». */
export function NewLeadsCard({
    leads,
    loading,
    error,
    canManage,
    busyLeadId,
    onTake,
}: NewLeadsCardProps) {
    return (
        <section className={cn(dashCard, 'p-5')} aria-labelledby="dashboard-new-leads">
            <div className="flex items-center justify-between gap-3">
                <h2 id="dashboard-new-leads" className={dashHeading}>
                    Новые заявки
                </h2>
                <Link
                    to="/admin/crm"
                    className="text-[13px] font-semibold text-brand-700 no-underline hover:text-brand-800 hover:underline dark:text-brand-400"
                >
                    Открыть все →
                </Link>
            </div>

            {loading ? <Skeleton className="mt-3 h-24 w-full" /> : null}
            {error ? (
                <p className={cn('mt-3 text-sm', dashMuted)}>Не удалось загрузить заявки.</p>
            ) : null}

            {leads !== undefined && leads.length === 0 ? (
                <div className="mt-3 rounded-xl border border-dashed border-line-strong p-7 text-center text-graphite dark:border-slate-700 dark:text-slate-400">
                    <p className="m-0 font-semibold text-ink dark:text-slate-100">
                        Все новые заявки разобраны
                    </p>
                    <p className="mt-1">Новые появятся здесь и придут в Telegram</p>
                </div>
            ) : null}

            {leads !== undefined && leads.length > 0 ? (
                <ul className="mt-2 flex flex-col" aria-label="Новые заявки">
                    {leads.map((lead) => (
                        <li
                            key={lead.id}
                            className="flex flex-wrap items-center gap-x-3.5 gap-y-2.5 border-t border-surface-strong py-3.5 dark:border-slate-800"
                        >
                            <span
                                aria-hidden="true"
                                className="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-full bg-surface-strong text-[13px] font-bold text-graphite dark:bg-slate-800 dark:text-slate-200"
                            >
                                {leadInitials(lead.name)}
                            </span>
                            <div className="min-w-0 flex-[1_1_260px]">
                                <p className="m-0 flex flex-wrap items-center gap-2">
                                    <Link
                                        to={`/admin/crm/${lead.id}`}
                                        className="font-semibold text-ink no-underline hover:text-brand-700 dark:text-slate-100"
                                    >
                                        {lead.name}
                                    </Link>
                                    {lead.b2b ? (
                                        <span className="rounded-[5px] bg-indigo-100 px-1.5 py-px text-[11px] font-bold text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                            B2B
                                        </span>
                                    ) : null}
                                    <span className={cn('text-xs', dashMuted)}>
                                        {formatLeadListTime(lead.createdAt)} · {leadOrigin(lead)}
                                    </span>
                                </p>
                                {lead.messagePreview !== null && lead.messagePreview !== '' ? (
                                    <p className="mt-0.5 text-[13px] text-graphite dark:text-slate-300">
                                        {lead.messagePreview}
                                    </p>
                                ) : null}
                            </div>
                            <div className="flex gap-2">
                                <a
                                    href={telHref(lead.phone)}
                                    className={cn(
                                        dashOutlineButton,
                                        'h-12 gap-1.5 px-3 text-[13px]',
                                    )}
                                    aria-label={`Позвонить: ${lead.name}`}
                                >
                                    <NavIcon name="phone" size={15} />
                                    Позвонить
                                </a>
                                {canManage ? (
                                    <button
                                        type="button"
                                        disabled={busyLeadId === lead.id}
                                        onClick={() => onTake(lead)}
                                        aria-label={`Взять в работу: ${lead.name}`}
                                        className="h-12 rounded-[9px] bg-brand-700 px-3 text-[13px] font-semibold text-white transition hover:bg-brand-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:opacity-60"
                                    >
                                        В работу
                                    </button>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            ) : null}
        </section>
    )
}

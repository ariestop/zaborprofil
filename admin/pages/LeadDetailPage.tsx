import { useEffect, useRef, useState, type ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useToast } from '../app/providers/toast-provider'
import {
    useLeadAssigneeMutation,
    useLeadAssigneesQuery,
    useLeadNoteMutation,
    useLeadQuery,
    useLeadReadMutation,
    useLeadStatusMutation,
} from '../entities/lead/api'
import { leadSourceLabel, leadStatusLabel, nextLeadAction } from '../entities/lead/model'
import {
    describeSpam,
    formatLeadEventTime,
    formatLeadReceived,
    LEAD_PILL_STYLES,
    leadInitials,
    leadPagePath,
    leadUtmText,
} from '../entities/lead/presentation'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { NavIcon } from '../layouts/nav-icons'
import { cn } from '../shared/lib/cn'
import { ErrorState, PageLoadingState } from '../shared/ui'
import { useCan } from '../stores/auth'
import type { LeadDetail, LeadEvent, LeadStatus } from '../types/api'

const NOTE_MAX_LENGTH = 2000
const NO_ASSIGNEE = 'none'

const sectionTitle =
    'm-0 text-xs font-semibold uppercase tracking-[0.06em] text-graphite dark:text-slate-400'
const card = 'rounded-[14px] border border-line bg-white dark:border-slate-800 dark:bg-slate-900'
const outlineAction =
    'inline-flex h-12 items-center gap-2 rounded-[11px] border border-line-strong bg-white px-3.5 text-sm font-semibold text-ink no-underline transition hover:bg-surface hover:text-ink focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800'

function phoneHref(phone: string): string {
    return `tel:${phone.replace(/[^\d+]/g, '')}`
}

function hasConsent(snapshot: Record<string, unknown> | null | undefined): boolean {
    return snapshot !== null && snapshot !== undefined && snapshot.consent === true
}

function statusWord(status: unknown): string {
    return typeof status === 'string' ? leadStatusLabel(status).toLowerCase() : '—'
}

interface TimelineItem {
    key: string
    time: string
    text: ReactNode
}

function describeEvent(event: LeadEvent): ReactNode {
    if (event.type === 'note') {
        return (
            <>
                Заметка: {event.body ?? ''}
                <span className="text-graphite dark:text-slate-400"> — {event.actorLabel}</span>
            </>
        )
    }

    if (event.type === 'status_changed') {
        return `Статус: ${statusWord(event.data.to)}`
    }

    const target = typeof event.data.toLabel === 'string' ? event.data.toLabel : null

    return target === null ? 'Ответственный снят' : `Назначен ответственный: ${target}`
}

function receivedText(lead: LeadDetail): string {
    if (lead.source === 'phone_call') {
        return 'Заявка создана вручную после звонка'
    }

    const path = leadPagePath(lead.pageUrl)

    return path === '—' ? 'Заявка получена' : `Заявка получена с формы на странице ${path}`
}

function timeline(lead: LeadDetail): TimelineItem[] {
    return [
        { key: 'received', time: formatLeadEventTime(lead.createdAt), text: receivedText(lead) },
        ...lead.events.map((event) => ({
            key: event.id,
            time: formatLeadEventTime(event.createdAt),
            text: describeEvent(event),
        })),
    ]
}

export interface LeadStatusChangeTarget {
    id: string
    name: string
    status: LeadStatus
}

interface LeadDetailPageProps {
    /** Идентификатор заявки; по умолчанию берётся из адреса `/admin/crm/:leadId`. */
    leadId?: string
    /** Куда ведёт ссылка «К списку заявок» на телефоне. */
    closeHref?: string
    /**
     * Смена статуса делегируется рабочему месту «Заявки», чтобы показать плашку «Отменить».
     * Без обработчика карточка меняет статус сама.
     */
    onChangeStatus?: (lead: LeadStatusChangeTarget, status: LeadStatus) => Promise<void>
}

/**
 * Карточка заявки. На широком экране показывается справа от списка в разделе «Заявки»,
 * на телефоне — отдельным экраном со ссылкой назад к списку.
 */
export default function LeadDetailPage({
    leadId: leadIdProp,
    closeHref,
    onChangeStatus,
}: LeadDetailPageProps = {}) {
    const params = useParams()
    const leadId = leadIdProp ?? params.leadId ?? ''
    const { push } = useToast()
    const canManage = useCan('leads.manage')
    const leadQuery = useLeadQuery(leadId)
    const assigneesQuery = useLeadAssigneesQuery()
    const statusMutation = useLeadStatusMutation()
    const assigneeMutation = useLeadAssigneeMutation()
    const noteMutation = useLeadNoteMutation()
    const readMutation = useLeadReadMutation()
    const [note, setNote] = useState('')
    const markedRead = useRef<string | null>(null)
    const backHref = closeHref ?? '/admin/crm'
    const lead = leadQuery.data

    // Открытая карточка считается прочитанной: в списке исчезает оранжевая точка.
    const needsRead = lead !== undefined && lead.readAt === null
    const { mutate: markRead } = readMutation
    useEffect(() => {
        if (needsRead && markedRead.current !== leadId) {
            markedRead.current = leadId
            markRead(leadId)
        }
    }, [needsRead, leadId, markRead])

    if (leadQuery.isPending) {
        return <PageLoadingState />
    }

    if (leadQuery.isError || lead === undefined) {
        return (
            <div className="grid gap-4">
                <Link
                    className="text-sm text-brand-700 hover:underline lg:hidden dark:text-brand-400"
                    to={backHref}
                >
                    ← К списку заявок
                </Link>
                <ErrorState
                    title="Не удалось загрузить заявку"
                    description="Заявка не найдена или нет права leads.view."
                />
            </div>
        )
    }

    const assignees = assigneesQuery.data?.items ?? []
    const assigneeOptions = [
        { value: NO_ASSIGNEE, label: 'Не назначен' },
        ...assignees.map((assignee) => ({ value: assignee.id, label: assignee.email })),
    ]
    if (
        lead.assignee !== null &&
        !assignees.some((assignee) => assignee.id === lead.assignee?.id)
    ) {
        assigneeOptions.push({
            value: lead.assignee.id,
            label: lead.assignee.email ?? 'Пользователь удалён',
        })
    }
    const nextAction = nextLeadAction(lead.status)
    const spam = describeSpam(lead.spamScore, lead.spamReasons)
    const consent = hasConsent(lead.consentSnapshot)

    const changeStatus = async (status: LeadStatus) => {
        if (onChangeStatus !== undefined) {
            await onChangeStatus({ id: lead.id, name: lead.name, status: lead.status }, status)

            return
        }

        try {
            await statusMutation.mutateAsync({ leadId: lead.id, status })
            push({ title: 'Статус обновлён', description: leadStatusLabel(status) })
        } catch (error) {
            push({
                title: 'Не удалось изменить статус',
                description: describeApiError(error, 'Повторите попытку.'),
            })
        }
    }

    const changeAssignee = async (value: string) => {
        try {
            await assigneeMutation.mutateAsync({
                leadId: lead.id,
                assigneeId: value === NO_ASSIGNEE ? null : value,
            })
            push({ title: 'Ответственный обновлён' })
        } catch (error) {
            push({
                title: 'Не удалось назначить ответственного',
                description: describeApiError(error, 'Повторите попытку.'),
            })
        }
    }

    const submitNote = async () => {
        const text = note.trim()
        if (text === '') {
            return
        }

        try {
            await noteMutation.mutateAsync({ leadId: lead.id, text })
            setNote('')
            push({ title: 'Заметка добавлена' })
        } catch (error) {
            push({
                title: 'Не удалось сохранить заметку',
                description: describeApiError(error, 'Повторите попытку.'),
            })
        }
    }

    const items = timeline(lead)

    return (
        <div
            className="@container mx-auto flex w-full max-w-[1240px] flex-col gap-5"
            data-testid="lead-detail"
        >
            <Link
                className="text-sm text-brand-700 hover:underline lg:hidden dark:text-brand-400"
                to={backHref}
            >
                ← К списку заявок
            </Link>

            <div className="flex flex-wrap items-center gap-4">
                <span
                    aria-hidden="true"
                    className="flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-full bg-surface-strong text-[17px] font-bold text-graphite dark:bg-slate-800 dark:text-slate-200"
                >
                    {leadInitials(lead.name)}
                </span>
                <div className="min-w-0 flex-[1_1_240px]">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <h1 className="m-0 break-words text-2xl font-bold">{lead.name}</h1>
                        <span
                            className={cn(
                                'rounded-full px-[9px] py-1 text-xs font-bold',
                                LEAD_PILL_STYLES[lead.status],
                            )}
                        >
                            {leadStatusLabel(lead.status)}
                        </span>
                    </div>
                    <p className="mt-1 text-graphite dark:text-slate-400">
                        {lead.phone} · {lead.email ?? 'email не указан'}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2" aria-label="Быстрые действия">
                    <a className={outlineAction} href={phoneHref(lead.phone)}>
                        <NavIcon name="phone" size={17} />
                        Позвонить
                    </a>
                    {lead.email !== null ? (
                        <a className={outlineAction} href={`mailto:${lead.email}`}>
                            <NavIcon name="send" size={17} />
                            Написать
                        </a>
                    ) : (
                        <button
                            type="button"
                            className={cn(outlineAction, 'cursor-not-allowed opacity-60')}
                            disabled
                            title="Email клиента не указан"
                        >
                            <NavIcon name="send" size={17} />
                            Написать
                        </button>
                    )}
                    <button
                        type="button"
                        className={outlineAction}
                        onClick={() =>
                            push({
                                title: 'Смета скоро появится',
                                description: 'Расчёт сметы прямо из заявки — в разработке.',
                            })
                        }
                    >
                        <NavIcon name="calculator" size={17} />
                        Смета
                        <span className="rounded-[5px] bg-surface-strong px-[5px] py-px text-[10px] font-bold text-graphite dark:bg-slate-800 dark:text-slate-300">
                            скоро
                        </span>
                    </button>
                    {nextAction !== null && canManage ? (
                        <button
                            type="button"
                            disabled={statusMutation.isPending}
                            onClick={() => void changeStatus(nextAction.status)}
                            className="h-12 rounded-[11px] bg-brand-700 px-4 text-sm font-bold text-white transition hover:bg-brand-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {nextAction.label}
                        </button>
                    ) : null}
                </div>
            </div>

            {/*
             * Широкая карточка: слева запрос и история, справа узкая колонка со служебными
             * блоками. Узкая — одна колонка в порядке: запрос, служебные блоки, история.
             */}
            <div className="grid items-start gap-4 @4xl:grid-cols-[minmax(0,1fr)_300px] @4xl:grid-rows-[auto_1fr] @4xl:gap-5">
                <section className={cn(card, 'min-w-0 p-5 @4xl:col-start-1 @4xl:row-start-1')}>
                    <h2 className={sectionTitle}>Запрос клиента</h2>
                    <p className="mt-2.5 whitespace-pre-wrap break-words text-[17px] leading-[1.6]">
                        {lead.message ?? 'Клиент не оставил сообщения.'}
                    </p>
                    <dl className="mt-4 grid grid-cols-1 gap-x-6 gap-y-3.5 border-t border-line pt-4 text-[13px] @md:grid-cols-2 @3xl:grid-cols-4 dark:border-slate-800">
                        <div className="min-w-0">
                            <dt className="text-graphite dark:text-slate-400">Источник</dt>
                            <dd className="m-0 mt-0.5">{leadSourceLabel(lead.source)}</dd>
                        </div>
                        <div className="min-w-0">
                            <dt className="text-graphite dark:text-slate-400">Страница</dt>
                            <dd className="m-0 mt-0.5 break-all">{leadPagePath(lead.pageUrl)}</dd>
                        </div>
                        <div className="min-w-0">
                            <dt className="text-graphite dark:text-slate-400">Получена</dt>
                            <dd className="m-0 mt-0.5">{formatLeadReceived(lead.createdAt)}</dd>
                        </div>
                        <div className="min-w-0">
                            <dt className="text-graphite dark:text-slate-400">UTM-метки</dt>
                            <dd className="m-0 mt-0.5 break-all">{leadUtmText(lead.utm)}</dd>
                        </div>
                    </dl>
                </section>

                <aside
                    aria-label="Работа с заявкой"
                    className="grid min-w-0 gap-4 @2xl:grid-cols-3 @4xl:col-start-2 @4xl:row-span-2 @4xl:row-start-1 @4xl:grid-cols-1"
                >
                    <section className={cn(card, 'flex flex-col gap-2 p-[18px]')}>
                        <label htmlFor="lead-owner" className={sectionTitle}>
                            Ответственный
                        </label>
                        <select
                            id="lead-owner"
                            value={lead.assignee?.id ?? NO_ASSIGNEE}
                            disabled={!canManage || assigneeMutation.isPending}
                            onChange={(event) => void changeAssignee(event.target.value)}
                            className="h-12 w-full min-w-0 rounded-[10px] border border-line-strong bg-white px-2.5 text-sm text-ink focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                        >
                            {assigneeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </section>

                    <section className={cn(card, 'p-[18px]')}>
                        <h2 className={sectionTitle}>Антиспам</h2>
                        <p className={cn('mt-2 font-semibold', spam.color)}>{spam.title}</p>
                        <div className="mt-2.5 h-1.5 overflow-hidden rounded-[3px] bg-line dark:bg-slate-800">
                            <div
                                className={cn('h-1.5', spam.barColor)}
                                style={{ width: `${spam.percent}%` }}
                            />
                        </div>
                        <p className="mt-2 text-[13px] text-graphite dark:text-slate-400">
                            {spam.text}
                        </p>
                        {canManage && lead.status !== 'spam' ? (
                            <button
                                type="button"
                                disabled={statusMutation.isPending}
                                onClick={() => void changeStatus('spam')}
                                className="mt-3 text-[13px] font-medium text-danger hover:underline focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 disabled:opacity-60 dark:text-red-400"
                            >
                                Отметить как спам
                            </button>
                        ) : null}
                    </section>

                    <section className={cn(card, 'p-[18px]')}>
                        <h2 className={sectionTitle}>Согласие на обработку данных</h2>
                        <p
                            className={cn(
                                'mt-2 font-semibold',
                                consent
                                    ? 'text-brand-800 dark:text-green-400'
                                    : 'text-amber-700 dark:text-amber-400',
                            )}
                        >
                            {consent ? 'Получено при отправке формы' : 'Согласие не зафиксировано'}
                        </p>
                        <p className="mt-0.5 text-[13px] text-graphite dark:text-slate-400">
                            {consent
                                ? 'Снимок политики сохранён вместе с заявкой'
                                : 'Заявка заведена вручную — подтвердите согласие клиента при разговоре'}
                        </p>
                        {consent ? (
                            <details className="mt-2 text-[13px]">
                                <summary className="cursor-pointer text-graphite dark:text-slate-400">
                                    Сохранённый снимок
                                </summary>
                                <pre className="mt-2 max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-surface p-2 font-mono text-xs dark:bg-slate-950">
                                    {JSON.stringify(lead.consentSnapshot, null, 2)}
                                </pre>
                            </details>
                        ) : null}
                    </section>
                </aside>

                <section className={cn(card, 'min-w-0 p-5 @4xl:col-start-1 @4xl:row-start-2')}>
                    <h2 className={sectionTitle}>История и заметки</h2>
                    <ol className="m-0 mt-4 list-none p-0" aria-label="История заявки">
                        {items.map((item, index) => (
                            <li
                                key={item.key}
                                data-testid="lead-event"
                                className="relative flex gap-3 pb-4 last:pb-0"
                            >
                                {index < items.length - 1 ? (
                                    <span
                                        className="absolute bottom-0 left-[3.5px] top-4 w-px bg-line-strong dark:bg-slate-700"
                                        aria-hidden="true"
                                    />
                                ) : null}
                                <span
                                    className="mt-[7px] h-2 w-2 shrink-0 rounded-full bg-graphite/60"
                                    aria-hidden="true"
                                />
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5 @md:flex-row @md:items-baseline @md:justify-between @md:gap-4">
                                    <span className="min-w-0 whitespace-pre-wrap break-words text-ink dark:text-slate-200">
                                        {item.text}
                                    </span>
                                    <span className="shrink-0 whitespace-nowrap text-xs text-graphite dark:text-slate-400">
                                        {item.time}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ol>
                    {canManage ? (
                        <div className="mt-5 flex flex-col gap-2 border-t border-line pt-4 dark:border-slate-800">
                            <label
                                htmlFor="lead-note"
                                className="text-[13px] text-graphite dark:text-slate-400"
                            >
                                Заметка для команды
                            </label>
                            <textarea
                                id="lead-note"
                                rows={3}
                                maxLength={NOTE_MAX_LENGTH}
                                placeholder="Например: перезвонить после 18:00, замер в субботу"
                                value={note}
                                onChange={(event) => setNote(event.target.value)}
                                className="box-border w-full resize-y rounded-[11px] border border-line-strong bg-white px-3 py-2.5 text-sm text-ink focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                            />
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="flex flex-wrap gap-x-3.5 gap-y-1.5 text-xs text-graphite dark:text-slate-400">
                                    <span>
                                        <Kbd>J</Kbd> <Kbd>K</Kbd> навигация
                                    </span>
                                    <span>
                                        <Kbd>E</Kbd> следующий статус
                                    </span>
                                    <span>
                                        <Kbd>S</Kbd> спам
                                    </span>
                                </span>
                                <button
                                    type="button"
                                    disabled={note.trim() === '' || noteMutation.isPending}
                                    onClick={() => void submitNote()}
                                    className="h-12 rounded-[10px] border border-line-strong bg-white px-3.5 text-[13px] font-semibold text-ink transition hover:bg-surface dark:hover:bg-slate-50 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                >
                                    Добавить заметку
                                </button>
                            </div>
                        </div>
                    ) : null}
                </section>
            </div>
        </div>
    )
}

function Kbd({ children }: { children: ReactNode }) {
    return (
        <kbd className="rounded-[5px] border border-line-strong px-1.5 py-px font-[inherit] text-graphite dark:border-slate-600 dark:text-slate-300">
            {children}
        </kbd>
    )
}

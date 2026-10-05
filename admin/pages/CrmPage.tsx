import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useMatch, useNavigate, useSearchParams } from 'react-router-dom'
import { useHotkeys } from 'react-hotkeys-hook'
import { useToast } from '../app/providers/toast-provider'
import {
    exportLeadsCsv,
    useLeadAssigneesQuery,
    useLeadsQuery,
    useLeadStatusMutation,
} from '../entities/lead/api'
import {
    leadSourceLabel,
    leadStatusLabel,
    nextLeadAction,
    type LeadFilters,
    type LeadListParams,
    type LeadSortField,
} from '../entities/lead/model'
import {
    detectPeriod,
    formatLeadListTime,
    leadInitials,
    periodRange,
    type LeadPeriod,
} from '../entities/lead/presentation'
import { CreateLeadDialog } from '../features/leads/CreateLeadDialog'
import { FilterSelect } from '../features/leads/FilterSelect'
import { useLeadSavedViews, type LeadSavedView } from '../features/leads/saved-views'
import { UndoToast, type UndoToastState } from '../features/leads/UndoToast'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { useDebouncedValue } from '../shared/hooks/use-debounced-value'
import { downloadTextFile } from '../shared/lib/download'
import { cn } from '../shared/lib/cn'
import { formatNumber } from '../shared/lib/format'
import { ErrorState } from '../shared/ui'
import { Pagination } from '../shared/ui/pagination'
import { NavIcon } from '../layouts/nav-icons'
import { TopbarActions } from '../layouts/topbar-slot'
import { useCan } from '../stores/auth'
import type { LeadStatus } from '../types/api'
import LeadDetailPage, { type LeadStatusChangeTarget } from './LeadDetailPage'

const PER_PAGE = 25
const STATUSES: LeadStatus[] = ['new', 'in_progress', 'done', 'spam']
const SORTS: LeadSortField[] = ['createdAt', 'updatedAt', 'name', 'status', 'source']
const WAITING_HOURS = 2
const DESKTOP_QUERY = '(min-width: 1024px)'

const TABS: Array<{ id: LeadStatus | 'all'; label: string }> = [
    { id: 'all', label: 'Все' },
    { id: 'new', label: 'Новые' },
    { id: 'in_progress', label: 'В работе' },
    { id: 'done', label: 'Готово' },
    { id: 'spam', label: 'Спам' },
]

const PERIOD_OPTIONS: Array<{ value: LeadPeriod; label: string }> = [
    { value: 'all', label: 'Период' },
    { value: 'today', label: 'Сегодня' },
    { value: 'yesterday', label: 'Вчера' },
    { value: '7d', label: 'Последние 7 дней' },
    { value: '30d', label: 'Последние 30 дней' },
    { value: 'custom', label: 'Свои даты…' },
]

function readParams(search: URLSearchParams): LeadListParams {
    const status = search.get('status')
    const sort = search.get('sort')
    const waiting = Number.parseInt(search.get('waiting') ?? '', 10)

    return {
        q: '',
        status: STATUSES.includes(status as LeadStatus) ? (status as LeadStatus) : 'all',
        source: search.get('source') ?? '',
        from: search.get('from') ?? '',
        to: search.get('to') ?? '',
        assignee: search.get('assignee') ?? 'all',
        b2b: search.get('b2b') === '1',
        waitingHours: Number.isFinite(waiting) && waiting > 0 ? waiting : 0,
        sort: SORTS.includes(sort as LeadSortField) ? (sort as LeadSortField) : 'createdAt',
        direction: search.get('direction') === 'asc' ? 'asc' : 'desc',
        page: Math.max(1, Number.parseInt(search.get('page') ?? '1', 10) || 1),
        perPage: PER_PAGE,
    }
}

function writeParams(params: LeadListParams): URLSearchParams {
    const search = new URLSearchParams()
    const entries: Array<[string, string, string]> = [
        ['status', params.status, 'all'],
        ['source', params.source, ''],
        ['from', params.from, ''],
        ['to', params.to, ''],
        ['assignee', params.assignee, 'all'],
        ['b2b', params.b2b ? '1' : '', ''],
        ['waiting', params.waitingHours > 0 ? String(params.waitingHours) : '', ''],
        ['sort', params.sort, 'createdAt'],
        ['direction', params.direction, 'desc'],
        ['page', String(params.page), '1'],
    ]
    for (const [key, value, fallback] of entries) {
        if (value !== fallback) {
            search.set(key, value)
        }
    }

    return search
}

function withSearch(path: string, search: URLSearchParams): string {
    const query = search.toString()

    return query === '' ? path : `${path}?${query}`
}

function statusMatchesTab(tab: LeadStatus | 'all', status: LeadStatus): boolean {
    return tab === 'all' ? status !== 'spam' : tab === status
}

function isDesktop(): boolean {
    return typeof window.matchMedia === 'function' && window.matchMedia(DESKTOP_QUERY).matches
}

const filterButton = 'h-8'
const chipBase =
    'h-[30px] rounded-full border px-2.5 text-xs transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500'
const chipOn =
    'border-brand-200 bg-brand-50 font-semibold text-brand-800 dark:border-brand-800 dark:bg-brand-900/30 dark:text-brand-200'
const chipOff =
    'border-line-strong bg-white font-medium text-graphite hover:bg-surface dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'

/**
 * Рабочее место по заявкам: слева список с вкладками статусов, фильтрами и быстрыми видами,
 * справа карточка выбранной заявки. Фильтры живут в адресе, поэтому сохраняются при переходе между заявками.
 * На телефоне список и карточка показываются по очереди.
 */
export default function CrmPage() {
    const { push } = useToast()
    const navigate = useNavigate()
    const leadId = useMatch('/admin/crm/:leadId')?.params.leadId
    const [searchParams, setSearchParams] = useSearchParams()
    const urlParams = useMemo(() => readParams(searchParams), [searchParams])
    const [searchText, setSearchText] = useState('')
    const debouncedSearch = useDebouncedValue(searchText)
    const params: LeadListParams = { ...urlParams, q: debouncedSearch.trim() }

    const leadsQuery = useLeadsQuery(params)
    const assigneesQuery = useLeadAssigneesQuery()
    const statusMutation = useLeadStatusMutation()
    const [exporting, setExporting] = useState(false)
    const [createOpen, setCreateOpen] = useState(false)
    const [customPeriod, setCustomPeriod] = useState(false)
    const [savingView, setSavingView] = useState(false)
    const [viewName, setViewName] = useState('')
    const [undo, setUndo] = useState<
        (UndoToastState & { leadId: string; from: LeadStatus }) | null
    >(null)
    const canExport = useCan('leads.export')
    const canManage = useCan('leads.manage')
    const savedViews = useLeadSavedViews()

    const update = (patch: Partial<LeadListParams>) => {
        setSearchParams(writeParams({ ...params, page: 1, ...patch }), { replace: true })
    }

    const exportCsv = async () => {
        const filters: LeadFilters = {
            q: params.q,
            status: params.status,
            source: params.source,
            from: params.from,
            to: params.to,
            assignee: params.assignee,
            b2b: params.b2b,
            waitingHours: params.waitingHours,
        }
        setExporting(true)
        try {
            const csv = await exportLeadsCsv(filters, params.sort, params.direction)
            downloadTextFile(`leads-${new Date().toISOString().slice(0, 10)}.csv`, csv)
        } catch (error) {
            push({
                title: 'Не удалось выгрузить CSV',
                description: describeApiError(error, 'Недостаточно прав или сервер недоступен.'),
            })
        } finally {
            setExporting(false)
        }
    }

    const data = leadsQuery.data
    const items = useMemo(() => data?.items ?? [], [data])
    const sources = data?.sources ?? []
    const assignees = assigneesQuery.data?.items ?? []
    const period = detectPeriod(params.from, params.to)
    const hasFilters =
        params.q !== '' ||
        params.status !== 'all' ||
        params.source !== '' ||
        params.from !== '' ||
        params.to !== '' ||
        params.assignee !== 'all' ||
        params.b2b ||
        params.waitingHours > 0

    const leadHref = useCallback(
        (id: string) => withSearch(`/admin/crm/${id}`, searchParams),
        [searchParams],
    )
    const listHref = withSearch('/admin/crm', searchParams)
    const selected = leadId !== undefined && leadId !== ''

    // На широком экране карточка справа всегда заполнена: открывается первая заявка списка.
    const firstId = items[0]?.id
    useEffect(() => {
        if (!selected && firstId !== undefined && isDesktop()) {
            navigate(leadHref(firstId), { replace: true })
        }
    }, [selected, firstId, navigate, leadHref])

    // J / K — следующая и предыдущая заявка в списке, как в почтовых клиентах.
    const moveSelection = (step: 1 | -1) => {
        if (items.length === 0) {
            return
        }
        const currentIndex = items.findIndex((item) => item.id === leadId)
        const nextIndex =
            currentIndex === -1
                ? step === 1
                    ? 0
                    : items.length - 1
                : Math.min(items.length - 1, Math.max(0, currentIndex + step))
        const next = items[nextIndex]
        if (next !== undefined && next.id !== leadId) {
            navigate(leadHref(next.id))
        }
    }

    const changeStatus = async (lead: LeadStatusChangeTarget, status: LeadStatus) => {
        if (status === lead.status) {
            return
        }

        try {
            await statusMutation.mutateAsync({ leadId: lead.id, status })
        } catch (error) {
            push({
                title: 'Не удалось изменить статус',
                description: describeApiError(error, 'Повторите попытку.'),
            })

            return
        }

        setUndo({
            id: Date.now(),
            leadId: lead.id,
            from: lead.status,
            text: `«${lead.name}» → ${leadStatusLabel(status)}`,
        })

        // Заявка ушла из текущей вкладки — переходим к соседней, как в макете.
        if (selected && lead.id === leadId && !statusMatchesTab(params.status, status)) {
            const index = items.findIndex((item) => item.id === lead.id)
            const neighbour = items[index + 1] ?? items[index - 1]
            navigate(neighbour === undefined ? listHref : leadHref(neighbour.id), { replace: true })
        }
    }

    const undoLast = () => {
        if (undo === null) {
            return
        }
        const { leadId: id, from } = undo
        setUndo(null)
        void statusMutation.mutateAsync({ leadId: id, status: from }).then(
            () => navigate(leadHref(id), { replace: true }),
            (error: unknown) =>
                push({
                    title: 'Не удалось отменить изменение',
                    description: describeApiError(error, 'Повторите попытку.'),
                }),
        )
    }

    const dismissUndo = useCallback(() => setUndo(null), [])

    const currentLead = items.find((item) => item.id === leadId)
    useHotkeys('j', () => moveSelection(1), [items, leadId, searchParams])
    useHotkeys('k', () => moveSelection(-1), [items, leadId, searchParams])
    useHotkeys(
        'e',
        () => {
            const action = currentLead === undefined ? null : nextLeadAction(currentLead.status)
            if (currentLead !== undefined && action !== null && canManage) {
                void changeStatus(
                    { id: currentLead.id, name: currentLead.name, status: currentLead.status },
                    action.status,
                )
            }
        },
        [currentLead, canManage, params.status, items, leadId],
    )
    useHotkeys(
        's',
        () => {
            if (currentLead !== undefined && canManage) {
                void changeStatus(
                    { id: currentLead.id, name: currentLead.name, status: currentLead.status },
                    currentLead.status === 'spam' ? 'new' : 'spam',
                )
            }
        },
        [currentLead, canManage, params.status, items, leadId],
    )

    const setPeriod = (next: LeadPeriod) => {
        if (next === 'all') {
            setCustomPeriod(false)
            update({ from: '', to: '' })
        } else if (next === 'custom') {
            setCustomPeriod(true)
        } else {
            setCustomPeriod(false)
            update(periodRange(next))
        }
    }

    const applyView = (view: LeadSavedView) => {
        const range =
            view.period === 'all' || view.period === 'custom'
                ? { from: view.from, to: view.to }
                : periodRange(view.period)
        setCustomPeriod(view.period === 'custom')
        setSearchText('')
        setSearchParams(
            writeParams({
                ...params,
                q: '',
                status: STATUSES.includes(view.status as LeadStatus)
                    ? (view.status as LeadStatus)
                    : 'all',
                source: view.source,
                assignee: view.assignee,
                b2b: view.b2b,
                waitingHours: view.waitingHours,
                from: range.from,
                to: range.to,
                page: 1,
            }),
            { replace: true },
        )
    }

    const saveView = () => {
        const name = viewName.trim()
        if (name === '') {
            return
        }
        savedViews.add({
            name,
            status: params.status,
            source: params.source,
            assignee: params.assignee,
            b2b: params.b2b,
            waitingHours: params.waitingHours,
            period,
            from: params.from,
            to: params.to,
        })
        setSavingView(false)
        setViewName('')
        push({ title: 'Вид сохранён', description: name })
    }

    const counts = data?.counts
    const tabCount = (id: LeadStatus | 'all'): string => {
        if (counts === undefined) {
            return ''
        }

        return formatNumber(
            id === 'all' ? counts.total - (counts.byStatus.spam ?? 0) : (counts.byStatus[id] ?? 0),
        )
    }

    const todayActive = period === 'today'

    return (
        <>
            <TopbarActions>
                {canExport ? (
                    <button
                        type="button"
                        disabled={exporting}
                        onClick={() => void exportCsv()}
                        className="h-10 rounded-[10px] border border-line-strong bg-white px-3.5 text-sm font-semibold text-graphite transition hover:bg-surface dark:hover:bg-slate-50 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                    >
                        Экспорт CSV
                    </button>
                ) : null}
                {canManage ? (
                    <button
                        type="button"
                        onClick={() => setCreateOpen(true)}
                        className="inline-flex h-10 items-center gap-1.5 rounded-[10px] bg-brand-700 px-3.5 text-sm font-semibold text-white transition hover:bg-brand-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                    >
                        <NavIcon name="plus" size={16} strokeWidth={2} />
                        Заявка после звонка
                    </button>
                ) : null}
            </TopbarActions>

            <div className="flex flex-1 flex-col items-stretch lg:flex-row">
                <section
                    aria-label="Список заявок"
                    className={cn(
                        'box-border flex min-w-0 flex-col border-r border-line bg-white lg:sticky lg:top-[65px] lg:h-[calc(100vh-65px)] lg:w-[380px] lg:shrink-0 lg:overflow-y-auto dark:border-slate-800 dark:bg-slate-900',
                        selected && 'hidden lg:flex',
                    )}
                >
                    <div className="flex flex-col gap-3 px-[18px] pb-2.5 pt-[18px]">
                        <div
                            role="tablist"
                            aria-label="Статус заявок"
                            className="flex gap-0.5 rounded-[11px] bg-line p-[3px] dark:bg-slate-800"
                        >
                            {TABS.map((tab) => {
                                const active = params.status === tab.id

                                return (
                                    <button
                                        key={tab.id}
                                        type="button"
                                        role="tab"
                                        aria-selected={active}
                                        onClick={() => update({ status: tab.id })}
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

                        <label className="flex h-10 items-center gap-2 rounded-[10px] border border-line-strong px-3 text-graphite focus-within:ring-2 focus-within:ring-brand-500 dark:border-slate-700 dark:text-slate-400">
                            <NavIcon name="search" size={16} />
                            <input
                                type="search"
                                aria-label="Поиск по заявкам"
                                placeholder="Имя, телефон, текст"
                                value={searchText}
                                onChange={(event) => {
                                    setSearchText(event.target.value)
                                    if (urlParams.page !== 1) {
                                        update({})
                                    }
                                }}
                                className="min-w-0 flex-1 border-0 bg-transparent text-sm text-ink outline-hidden placeholder:text-graphite dark:text-slate-100"
                            />
                        </label>

                        <div className="flex flex-wrap gap-1.5">
                            <FilterSelect
                                label="Источник заявки"
                                value={params.source === '' ? 'all' : params.source}
                                active={params.source !== ''}
                                onChange={(value) =>
                                    update({ source: value === 'all' ? '' : value })
                                }
                                options={[
                                    { value: 'all', label: 'Все источники' },
                                    ...sources.map((source) => ({
                                        value: source,
                                        label: leadSourceLabel(source),
                                    })),
                                ]}
                            />
                            <FilterSelect
                                label="Ответственный"
                                value={params.assignee}
                                active={params.assignee !== 'all'}
                                onChange={(value) => update({ assignee: value })}
                                options={[
                                    { value: 'all', label: 'Любой ответственный' },
                                    { value: 'me', label: 'Мои заявки' },
                                    { value: 'none', label: 'Без ответственного' },
                                    ...assignees.map((assignee) => ({
                                        value: assignee.id,
                                        label: assignee.email,
                                    })),
                                ]}
                            />
                            <FilterSelect
                                label="Период"
                                value={customPeriod ? 'custom' : period}
                                active={period !== 'all' || customPeriod}
                                onChange={(value) => setPeriod(value as LeadPeriod)}
                                options={PERIOD_OPTIONS}
                            />
                        </div>

                        {customPeriod || period === 'custom' ? (
                            <div className="grid grid-cols-2 gap-2">
                                <label className="grid gap-1 text-xs text-graphite dark:text-slate-400">
                                    С
                                    <input
                                        type="date"
                                        aria-label="Дата от"
                                        value={params.from}
                                        onChange={(event) => update({ from: event.target.value })}
                                        className="h-9 rounded-lg border border-line-strong bg-white px-2 text-[13px] text-ink dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                    />
                                </label>
                                <label className="grid gap-1 text-xs text-graphite dark:text-slate-400">
                                    По
                                    <input
                                        type="date"
                                        aria-label="Дата до"
                                        value={params.to}
                                        onChange={(event) => update({ to: event.target.value })}
                                        className="h-9 rounded-lg border border-line-strong bg-white px-2 text-[13px] text-ink dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                    />
                                </label>
                            </div>
                        ) : null}

                        <div className="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                aria-pressed={todayActive}
                                onClick={() => setPeriod(todayActive ? 'all' : 'today')}
                                className={cn(chipBase, todayActive ? chipOn : chipOff)}
                            >
                                Сегодня
                            </button>
                            <button
                                type="button"
                                aria-pressed={params.b2b}
                                onClick={() => update({ b2b: !params.b2b })}
                                className={cn(chipBase, params.b2b ? chipOn : chipOff)}
                            >
                                Юрлица B2B
                            </button>
                            <button
                                type="button"
                                aria-pressed={params.waitingHours > 0}
                                onClick={() =>
                                    update({
                                        waitingHours: params.waitingHours > 0 ? 0 : WAITING_HOURS,
                                    })
                                }
                                className={cn(chipBase, params.waitingHours > 0 ? chipOn : chipOff)}
                            >
                                Без ответа больше {WAITING_HOURS} ч
                            </button>
                            {savedViews.views.map((view) => (
                                <span
                                    key={view.id}
                                    className={cn(
                                        chipBase,
                                        chipOff,
                                        'inline-flex items-center gap-1 pr-1',
                                    )}
                                >
                                    <button
                                        type="button"
                                        onClick={() => applyView(view)}
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
                                    saveView()
                                }}
                            >
                                <input
                                    aria-label="Название вида"
                                    placeholder="Название, например «B2B без ответа»"
                                    maxLength={40}
                                    autoFocus
                                    value={viewName}
                                    onChange={(event) => setViewName(event.target.value)}
                                    className="h-9 min-w-0 flex-1 rounded-lg border border-line-strong bg-white px-2.5 text-[13px] dark:border-slate-700 dark:bg-slate-900"
                                />
                                <button
                                    type="submit"
                                    disabled={viewName.trim() === ''}
                                    className="h-9 rounded-lg bg-brand-700 px-3 text-[13px] font-semibold text-white disabled:opacity-60"
                                >
                                    Сохранить
                                </button>
                            </form>
                        ) : null}

                        {hasFilters ? (
                            <div>
                                <button
                                    type="button"
                                    className={cn(
                                        filterButton,
                                        'rounded-lg px-1 text-[13px] font-medium text-brand-700 hover:underline dark:text-brand-400',
                                    )}
                                    onClick={() => {
                                        setSearchText('')
                                        setCustomPeriod(false)
                                        setSearchParams(new URLSearchParams(), { replace: true })
                                    }}
                                >
                                    Сбросить фильтры
                                </button>
                            </div>
                        ) : null}
                    </div>

                    <div className="flex flex-col gap-0.5 px-2 pb-4">
                        {leadsQuery.isError ? (
                            <ErrorState
                                title="Не удалось загрузить заявки"
                                description="Проверьте endpoint /admin/api/leads и право leads.view."
                            />
                        ) : null}

                        {leadsQuery.isPending ? (
                            <p className="m-2 text-sm text-graphite">Загрузка...</p>
                        ) : null}

                        {data !== undefined && items.length === 0 ? (
                            <div className="m-2 rounded-xl border border-dashed border-line-strong p-6 text-center text-graphite dark:border-slate-700">
                                <p className="m-0 font-semibold text-ink dark:text-slate-100">
                                    {hasFilters ? 'Ничего не найдено' : 'Здесь пусто'}
                                </p>
                                <p className="m-0 mt-1 text-[13px]">
                                    {hasFilters
                                        ? 'Измените поисковый запрос или сбросьте фильтры'
                                        : 'Все заявки в этом статусе разобраны'}
                                </p>
                            </div>
                        ) : null}

                        {items.length > 0 ? (
                            <ul
                                className="m-0 flex list-none flex-col gap-0.5 p-0"
                                aria-label="Заявки"
                            >
                                {items.map((lead) => {
                                    const isCurrent = lead.id === leadId
                                    const unread = lead.readAt === null && lead.status === 'new'

                                    return (
                                        <li key={lead.id} data-testid="lead-row">
                                            <Link
                                                to={leadHref(lead.id)}
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
                                                                unread
                                                                    ? 'font-bold'
                                                                    : 'font-medium',
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
                                })}
                            </ul>
                        ) : null}

                        {data !== undefined && data.pages > 1 ? (
                            <div className="mt-2 px-2">
                                <Pagination
                                    page={data.page}
                                    pages={data.pages}
                                    total={data.total}
                                    onPageChange={(page) => update({ page })}
                                />
                            </div>
                        ) : null}
                    </div>
                </section>

                <section
                    aria-label="Карточка заявки"
                    className={cn(
                        'box-border min-w-0 flex-1 px-4 pb-8 pt-6 lg:px-7',
                        !selected && 'hidden lg:block',
                    )}
                >
                    {selected ? (
                        <LeadDetailPage
                            key={leadId}
                            leadId={leadId}
                            closeHref={listHref}
                            onChangeStatus={changeStatus}
                        />
                    ) : (
                        <div className="mx-auto my-20 max-w-[360px] text-center text-graphite dark:text-slate-400">
                            <p className="m-0 text-lg font-bold text-ink dark:text-slate-100">
                                Выберите заявку слева
                            </p>
                            <p className="m-0 mt-1.5">Или переключите статус вверху списка</p>
                        </div>
                    )}
                </section>
            </div>

            <UndoToast toast={undo} onUndo={undoLast} onDismiss={dismissUndo} />

            <CreateLeadDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                onCreated={(id, name) => {
                    setCreateOpen(false)
                    push({ title: 'Заявка создана', description: name })
                    setSearchText('')
                    navigate(`/admin/crm/${id}`)
                }}
            />
        </>
    )
}

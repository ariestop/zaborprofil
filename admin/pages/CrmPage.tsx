import { useCallback, useEffect, useMemo, useState } from 'react'
import { useMatch, useNavigate, useSearchParams } from 'react-router-dom'
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
} from '../entities/lead/model'
import { detectPeriod, periodRange, type LeadPeriod } from '../entities/lead/presentation'
import { CreateLeadDialog } from '../features/leads/CreateLeadDialog'
import {
    isLeadStatus,
    readLeadListParams,
    statusMatchesTab,
    withSearch,
    writeLeadListParams,
} from '../features/leads/crm-url-params'
import { FilterSelect } from '../features/leads/FilterSelect'
import { LeadListRow } from '../features/leads/LeadListRow'
import { LeadQuickFilters } from '../features/leads/LeadQuickFilters'
import { LeadStatusTabs } from '../features/leads/LeadStatusTabs'
import { useLeadSavedViews, type LeadSavedView } from '../features/leads/saved-views'
import { UndoToast, type UndoToastState } from '../features/leads/UndoToast'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { useDebouncedValue } from '../shared/hooks/use-debounced-value'
import { downloadTextFile } from '../shared/lib/download'
import { cn } from '../shared/lib/cn'
import { ErrorState } from '../shared/ui'
import { Pagination } from '../shared/ui/pagination'
import { NavIcon } from '../layouts/nav-icons'
import { TopbarActions } from '../layouts/topbar-slot'
import { useCan } from '../stores/auth'
import type { LeadStatus } from '../types/api'
import LeadDetailPage, { type LeadStatusChangeTarget } from './LeadDetailPage'

const WAITING_HOURS = 2
const DESKTOP_QUERY = '(min-width: 1024px)'

const PERIOD_OPTIONS: Array<{ value: LeadPeriod; label: string }> = [
    { value: 'all', label: 'Период' },
    { value: 'today', label: 'Сегодня' },
    { value: 'yesterday', label: 'Вчера' },
    { value: '7d', label: 'Последние 7 дней' },
    { value: '30d', label: 'Последние 30 дней' },
    { value: 'custom', label: 'Свои даты…' },
]

function isDesktop(): boolean {
    return typeof window.matchMedia === 'function' && window.matchMedia(DESKTOP_QUERY).matches
}

const filterButton = 'h-8'

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
    const urlParams = useMemo(() => readLeadListParams(searchParams), [searchParams])
    const [searchText, setSearchText] = useState('')
    const debouncedSearch = useDebouncedValue(searchText)
    const params: LeadListParams = { ...urlParams, q: debouncedSearch.trim() }

    const leadsQuery = useLeadsQuery(params)
    const assigneesQuery = useLeadAssigneesQuery()
    const statusMutation = useLeadStatusMutation()
    const [exporting, setExporting] = useState(false)
    const [createOpen, setCreateOpen] = useState(false)
    const [customPeriod, setCustomPeriod] = useState(false)
    const [undo, setUndo] = useState<
        (UndoToastState & { leadId: string; from: LeadStatus }) | null
    >(null)
    const canExport = useCan('leads.export')
    const canManage = useCan('leads.manage')
    const savedViews = useLeadSavedViews()

    const update = (patch: Partial<LeadListParams>) => {
        setSearchParams(writeLeadListParams({ ...params, page: 1, ...patch }), { replace: true })
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
            writeLeadListParams({
                ...params,
                q: '',
                status: isLeadStatus(view.status) ? view.status : 'all',
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

    const saveView = (name: string) => {
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
        push({ title: 'Вид сохранён', description: name })
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
                        className="h-12 rounded-[10px] border border-line-strong bg-white px-3.5 text-sm font-semibold text-graphite transition hover:bg-surface dark:hover:bg-slate-50 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                    >
                        Экспорт CSV
                    </button>
                ) : null}
                {canManage ? (
                    <button
                        type="button"
                        onClick={() => setCreateOpen(true)}
                        className="inline-flex h-12 items-center gap-1.5 rounded-[10px] bg-brand-700 px-3.5 text-sm font-semibold text-white transition hover:bg-brand-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
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
                        <LeadStatusTabs
                            status={params.status}
                            counts={data?.counts}
                            onChange={(status) => update({ status })}
                        />

                        <label className="flex h-12 items-center gap-2 rounded-[10px] border border-line-strong px-3 text-graphite focus-within:ring-2 focus-within:ring-brand-500 dark:border-slate-700 dark:text-slate-400">
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
                                        className="h-12 rounded-lg border border-line-strong bg-white px-2 text-[13px] text-ink dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                    />
                                </label>
                                <label className="grid gap-1 text-xs text-graphite dark:text-slate-400">
                                    По
                                    <input
                                        type="date"
                                        aria-label="Дата до"
                                        value={params.to}
                                        onChange={(event) => update({ to: event.target.value })}
                                        className="h-12 rounded-lg border border-line-strong bg-white px-2 text-[13px] text-ink dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                    />
                                </label>
                            </div>
                        ) : null}

                        <LeadQuickFilters
                            todayActive={todayActive}
                            b2b={params.b2b}
                            waiting={params.waitingHours > 0}
                            waitingHours={WAITING_HOURS}
                            savedViews={savedViews}
                            onToggleToday={() => setPeriod(todayActive ? 'all' : 'today')}
                            onToggleB2b={() => update({ b2b: !params.b2b })}
                            onToggleWaiting={() =>
                                update({
                                    waitingHours: params.waitingHours > 0 ? 0 : WAITING_HOURS,
                                })
                            }
                            onApplyView={applyView}
                            onSaveView={saveView}
                        />

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
                                {items.map((lead) => (
                                    <LeadListRow
                                        key={lead.id}
                                        lead={lead}
                                        href={leadHref(lead.id)}
                                        isCurrent={lead.id === leadId}
                                    />
                                ))}
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

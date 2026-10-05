import { useCallback, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useToast } from '../app/providers/toast-provider'
import {
    useLeadDashboardQuery,
    useLeadStatusMutation,
    useLeadSummaryQuery,
    useLeadsQuery,
} from '../entities/lead/api'
import type { LeadListParams } from '../entities/lead/model'
import { usePagesQuery } from '../entities/page/api'
import {
    useAssetBuildRunMutation,
    useAssetBuildStatusQuery,
    useSystemBackupsQuery,
    useSystemObservabilityQuery,
    useSystemOverviewQuery,
} from '../entities/system/api'
import { AttentionCard } from '../features/dashboard/AttentionCard'
import { buildAttentionItems } from '../features/dashboard/attention'
import { NewLeadsCard } from '../features/dashboard/NewLeadsCard'
import { buildObservabilityTiles } from '../features/dashboard/observability'
import {
    KpiGrid,
    LeadsChartCard,
    MonitoringCard,
    SiteStateCard,
} from '../features/dashboard/RightColumn'
import { dashMuted, dashOutlineButton } from '../features/dashboard/styles'
import {
    buildSiteState,
    countPublications,
    greeting,
    todayLabel,
} from '../features/dashboard/summary'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { CreateLeadDialog } from '../features/leads/CreateLeadDialog'
import { UndoToast, type UndoToastState } from '../features/leads/UndoToast'
import { NavIcon, type NavIconName } from '../layouts/nav-icons'
import { TopbarActions } from '../layouts/topbar-slot'
import { cn } from '../shared/lib/cn'
import { Dropdown } from '../shared/ui'
import { useAuthStore } from '../stores/auth'
import type { AdminPermission } from '../entities/user/permissions'
import type { LeadItem } from '../types/api'

/** На сводке показываем четыре самые свежие неразобранные заявки. */
const NEW_LEADS_PARAMS: LeadListParams = {
    q: '',
    status: 'new',
    source: '',
    from: '',
    to: '',
    assignee: 'all',
    b2b: false,
    waitingHours: 0,
    sort: 'createdAt',
    direction: 'desc',
    page: 1,
    perPage: 4,
}

interface QuickAction {
    label: string
    href: string
    icon: NavIconName
    permission: AdminPermission
}

const quickActions: QuickAction[] = [
    {
        label: 'Новая страница',
        href: '/admin/pages/new',
        icon: 'pages',
        permission: 'pages.create',
    },
    { label: 'Загрузить фото', href: '/admin/media', icon: 'media', permission: 'media.upload' },
    { label: 'Редирект', href: '/admin/seo', icon: 'redirect', permission: 'seo.edit' },
]

export default function DashboardPage() {
    const navigate = useNavigate()
    const { push } = useToast()
    const permissions = useAuthStore((state) => state.permissions)
    const userName = useAuthStore((state) => state.userName)
    const canViewLeads = permissions.includes('leads.view')
    const canManageLeads = permissions.includes('leads.manage')
    const canViewPages = permissions.includes('pages.view')
    const canViewSystem = permissions.includes('system.view')

    const summaryQuery = useLeadSummaryQuery()
    const dashboardQuery = useLeadDashboardQuery()
    const newLeadsQuery = useLeadsQuery(NEW_LEADS_PARAMS, canViewLeads)
    const pagesQuery = usePagesQuery({ enabled: canViewPages })
    const overviewQuery = useSystemOverviewQuery()
    const backupsQuery = useSystemBackupsQuery()
    const observabilityQuery = useSystemObservabilityQuery()
    const buildQuery = useAssetBuildStatusQuery()
    const rebuild = useAssetBuildRunMutation()
    const statusMutation = useLeadStatusMutation()

    const [createOpen, setCreateOpen] = useState(false)
    const [undo, setUndo] = useState<(UndoToastState & { leadId: string }) | null>(null)
    const [busyLeadId, setBusyLeadId] = useState<string | null>(null)

    const hasBackups =
        backupsQuery.data === undefined ? undefined : backupsQuery.data.latestBackup !== null
    const buildStatus = buildQuery.data?.status
    const oldestNewAt = dashboardQuery.data?.oldestNewAt ?? null

    const attention = buildAttentionItems({
        newLeads: summaryQuery.data?.new,
        oldestWaitMs:
            oldestNewAt === null ? null : dashboardQuery.dataUpdatedAt - Date.parse(oldestNewAt),
        warnings: overviewQuery.data?.warnings,
        hasBackups,
        failedMessages: observabilityQuery.data?.queue.failed,
        serverErrorsLastHour: observabilityQuery.data?.serverErrors.lastHour,
        buildFailed: buildStatus === undefined ? undefined : buildStatus === 'failed',
    })
    const attentionLoading =
        summaryQuery.isLoading || overviewQuery.isLoading || backupsQuery.isLoading

    const siteRows = buildSiteState({
        platformOk:
            overviewQuery.data === undefined ? undefined : overviewQuery.data.status === 'ok',
        phpVersion: overviewQuery.data?.environment.phpVersion,
        buildStatus,
        hasBackups,
        disk: observabilityQuery.data?.disk.status,
        failedMessages: observabilityQuery.data?.queue.failed,
    })

    const takeLead = async (lead: LeadItem) => {
        setBusyLeadId(lead.id)
        try {
            await statusMutation.mutateAsync({ leadId: lead.id, status: 'in_progress' })
            setUndo({
                id: Date.now(),
                leadId: lead.id,
                text: `Заявка «${lead.name}» взята в работу`,
            })
        } catch (error) {
            push({
                title: 'Не удалось взять заявку в работу',
                description: describeApiError(error, 'Повторите попытку.'),
            })
        } finally {
            setBusyLeadId(null)
        }
    }

    const undoTake = () => {
        if (undo === null) {
            return
        }
        const { leadId } = undo
        setUndo(null)
        statusMutation.mutate(
            { leadId, status: 'new' },
            {
                onError: (error) =>
                    push({
                        title: 'Не удалось отменить изменение',
                        description: describeApiError(error, 'Повторите попытку.'),
                    }),
            },
        )
    }

    const dismissUndo = useCallback(() => setUndo(null), [])

    const startRebuild = () => {
        rebuild.mutate(undefined, {
            onSuccess: () =>
                push({
                    title: 'Сборка запущена',
                    description: 'Это займёт пару минут: страница обновится сама.',
                }),
            onError: (error) =>
                push({
                    title: 'Не удалось запустить сборку',
                    description: describeApiError(error, 'Повторите попытку.'),
                }),
        })
    }

    const createItems = [
        ...(permissions.includes('pages.create')
            ? [
                  {
                      key: 'page',
                      label: 'Новая страница',
                      onSelect: () => navigate('/admin/pages/new'),
                  },
              ]
            : []),
        ...(permissions.includes('media.upload')
            ? [{ key: 'media', label: 'Загрузить фото', onSelect: () => navigate('/admin/media') }]
            : []),
        ...(permissions.includes('seo.edit')
            ? [
                  {
                      key: 'redirect',
                      label: 'Добавить редирект',
                      onSelect: () => navigate('/admin/seo'),
                  },
              ]
            : []),
        ...(canManageLeads
            ? [{ key: 'lead', label: 'Заявка после звонка', onSelect: () => setCreateOpen(true) }]
            : []),
    ]

    const newCount = summaryQuery.data?.new
    const showAttention = canViewLeads || canViewSystem
    const showMonitoring = canViewSystem && observabilityQuery.isSuccess

    return (
        <>
            <TopbarActions>
                {createItems.length > 0 ? (
                    <Dropdown
                        items={createItems}
                        trigger={
                            <button
                                type="button"
                                className="inline-flex h-10 items-center gap-1.5 rounded-[10px] bg-brand-700 px-3.5 text-sm font-semibold text-white transition hover:bg-brand-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                            >
                                <NavIcon name="plus" size={16} strokeWidth={2} />
                                Создать
                            </button>
                        }
                    />
                ) : null}
            </TopbarActions>

            <div className="flex flex-col gap-5 p-4 sm:p-7">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="m-0 text-[26px] font-bold tracking-[-0.01em]">
                            {greeting(new Date(), userName)}
                        </h1>
                        <p className={cn('mt-1', dashMuted)}>
                            {todayLabel()}
                            {newCount === undefined ? '' : ` · новых заявок: ${newCount}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {quickActions
                            .filter((action) => permissions.includes(action.permission))
                            .map((action) => (
                                <Link
                                    key={action.href}
                                    to={action.href}
                                    className={cn(
                                        dashOutlineButton,
                                        'h-[38px] gap-1.5 rounded-[10px] bg-white px-3 text-[13px] dark:bg-slate-900',
                                    )}
                                >
                                    <NavIcon name={action.icon} size={16} />
                                    {action.label}
                                </Link>
                            ))}
                    </div>
                </div>

                <div className="flex flex-wrap items-start gap-5">
                    <div className="flex min-w-0 flex-[2_1_560px] flex-col gap-5">
                        {showAttention ? (
                            <AttentionCard
                                items={attention}
                                loading={attentionLoading}
                                rebuilding={rebuild.isPending}
                                onRebuild={startRebuild}
                            />
                        ) : null}
                        {canViewLeads ? (
                            <NewLeadsCard
                                leads={newLeadsQuery.data?.items}
                                loading={newLeadsQuery.isPending}
                                error={newLeadsQuery.isError}
                                canManage={canManageLeads}
                                busyLeadId={busyLeadId}
                                onTake={(lead) => void takeLead(lead)}
                            />
                        ) : null}
                    </div>

                    <div className="flex min-w-0 flex-[1_1_320px] flex-col gap-5">
                        <KpiGrid
                            newCount={summaryQuery.data?.new}
                            inProgress={summaryQuery.data?.byStatus.in_progress}
                            publications={
                                pagesQuery.data === undefined
                                    ? undefined
                                    : countPublications(pagesQuery.data)
                            }
                            doneLastWeek={dashboardQuery.data?.doneLastWeek}
                            canViewLeads={canViewLeads}
                            canViewPages={canViewPages}
                        />
                        {showMonitoring ? (
                            <MonitoringCard
                                tiles={buildObservabilityTiles(observabilityQuery.data)}
                            />
                        ) : null}
                        {canViewLeads ? (
                            <LeadsChartCard
                                data={dashboardQuery.data}
                                loading={dashboardQuery.isPending}
                                error={dashboardQuery.isError}
                            />
                        ) : null}
                        {canViewSystem ? (
                            <SiteStateCard rows={siteRows} loading={overviewQuery.isPending} />
                        ) : null}
                    </div>
                </div>
            </div>

            <UndoToast toast={undo} onUndo={undoTake} onDismiss={dismissUndo} />
            <CreateLeadDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                onCreated={(id, name) => {
                    setCreateOpen(false)
                    push({ title: 'Заявка создана', description: name })
                    navigate(`/admin/crm/${id}`)
                }}
            />
        </>
    )
}

import { Suspense, lazy } from 'react'
import { Link } from 'react-router-dom'
import { useLeadSummaryQuery } from '../entities/lead/api'
import { LEAD_STATUS_LABELS } from '../entities/lead/model'
import { useSystemBackupsQuery, useSystemOverviewQuery } from '../entities/system/api'
import { buildAttentionItems, type AttentionTone } from '../features/dashboard/attention'
import { cn } from '../shared/lib/cn'
import { formatDateTime, formatNumber } from '../shared/lib/format'
import { environmentName } from '../shared/lib/system-labels'
import { Card, ErrorState, PageHeader, Skeleton } from '../shared/ui'
import type { LeadStatus } from '../types/api'

const LeadsStatusChart = lazy(() => import('../widgets/LeadsStatusChart'))

const markerTone: Record<AttentionTone, string> = {
  leads: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
  critical: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
  warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
}

const quickActions = [
  { label: 'Новая страница', href: '/admin/pages/new' },
  { label: 'Загрузить фото', href: '/admin/media' },
  { label: 'Добавить редирект', href: '/admin/seo' },
  { label: 'Все заявки', href: '/admin/crm' },
]

const KPI_STATUSES: LeadStatus[] = ['new', 'in_progress', 'done']

function todayLabel(): string {
  const label = new Date().toLocaleDateString('ru-RU', { weekday: 'long', day: 'numeric', month: 'long' })

  return label.charAt(0).toUpperCase() + label.slice(1)
}

export default function DashboardPage() {
  const leadsQuery = useLeadSummaryQuery()
  const overviewQuery = useSystemOverviewQuery()
  const backupsQuery = useSystemBackupsQuery()

  const attention = buildAttentionItems({
    newLeads: leadsQuery.data?.new,
    warnings: overviewQuery.data?.warnings,
    hasBackups: backupsQuery.data === undefined ? undefined : backupsQuery.data.latestBackup !== null,
  })
  const attentionLoading = leadsQuery.isPending || overviewQuery.isPending || backupsQuery.isPending

  return (
    <div className="grid gap-5">
      <PageHeader
        title="Сводка"
        description={todayLabel()}
        actions={(
          <div className="flex flex-wrap gap-2">
            {quickActions.map((action) => (
              <Link
                key={action.href + action.label}
                to={action.href}
                className="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
              >
                {action.label}
              </Link>
            ))}
          </div>
        )}
      />

      <div className="grid gap-5 lg:grid-cols-3">
        <div className="grid content-start gap-5 lg:col-span-2">
          <Card title="Требует внимания">
            {attentionLoading && attention.length === 0 ? <Skeleton className="h-16 w-full" /> : null}
            {!attentionLoading && attention.length === 0 ? (
              <p className="text-sm text-slate-600 dark:text-slate-300">Всё в порядке: новых заявок нет, сервер не сообщает о проблемах.</p>
            ) : null}
            {attention.length > 0 ? (
              <ul className="divide-y divide-slate-100 dark:divide-slate-800" aria-label="Задачи, требующие внимания">
                {attention.map((item) => (
                  <li key={item.id} className="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 first:pt-0 last:pb-0">
                    <span
                      aria-hidden="true"
                      className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-base font-bold', markerTone[item.tone])}
                    >
                      {item.marker}
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="font-semibold">{item.title}</p>
                      <p className="text-sm text-slate-500 dark:text-slate-400">{item.description}</p>
                    </div>
                    <Link
                      to={item.href}
                      className="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800"
                    >
                      {item.actionLabel}
                    </Link>
                  </li>
                ))}
              </ul>
            ) : null}
          </Card>

          <Card title="Заявки по статусам">
            <Suspense fallback={<Skeleton className="h-40 w-full" />}>
              {leadsQuery.isPending ? <Skeleton className="h-40 w-full" /> : null}
              {leadsQuery.isError ? (
                <ErrorState title="Не удалось загрузить данные по заявкам" description="Нет права leads.view или сервер недоступен." />
              ) : null}
              {leadsQuery.isSuccess ? <LeadsStatusChart byStatus={leadsQuery.data.byStatus} statuses={leadsQuery.data.statuses} /> : null}
            </Suspense>
          </Card>
        </div>

        <div className="grid content-start gap-5">
          <div className="grid grid-cols-2 gap-3">
            {KPI_STATUSES.map((status) => (
              <Link
                key={status}
                to={`/admin/crm?status=${status}`}
                className="rounded-xl border border-slate-200 p-4 transition hover:border-emerald-300 dark:border-slate-800 dark:hover:border-emerald-800"
              >
                <span className="block text-sm text-slate-500 dark:text-slate-400">{LEAD_STATUS_LABELS[status]}</span>
                <span className={cn('mt-1 block text-2xl font-bold', status === 'new' && (leadsQuery.data?.new ?? 0) > 0 && 'text-orange-700 dark:text-orange-400')}>
                  {leadsQuery.data === undefined ? '—' : formatNumber(leadsQuery.data.byStatus[status] ?? 0)}
                </span>
              </Link>
            ))}
            <Link
              to="/admin/crm"
              className="rounded-xl border border-slate-200 p-4 transition hover:border-emerald-300 dark:border-slate-800 dark:hover:border-emerald-800"
            >
              <span className="block text-sm text-slate-500 dark:text-slate-400">Всего</span>
              <span className="mt-1 block text-2xl font-bold">{leadsQuery.data === undefined ? '—' : formatNumber(leadsQuery.data.total)}</span>
            </Link>
          </div>

          <Card title="Состояние сайта">
            {overviewQuery.isPending ? <Skeleton className="h-20 w-full" /> : null}
            {overviewQuery.isError ? (
              <p className="text-sm text-slate-500 dark:text-slate-400">Нет доступа к состоянию сервера.</p>
            ) : null}
            {overviewQuery.isSuccess ? (
              <dl className="grid gap-2 text-sm">
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-slate-500 dark:text-slate-400">Сайт</dt>
                  <dd className={overviewQuery.data.status === 'ok' ? 'font-semibold text-emerald-700 dark:text-emerald-400' : 'font-semibold text-red-700 dark:text-red-400'}>
                    {overviewQuery.data.status === 'ok' ? 'Работает' : 'Есть сбои'}
                  </dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-slate-500 dark:text-slate-400">Окружение</dt>
                  <dd className="font-medium">{environmentName(overviewQuery.data.environment.appEnv)} · PHP {overviewQuery.data.environment.phpVersion}</dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-slate-500 dark:text-slate-400">Предупреждения</dt>
                  <dd className={overviewQuery.data.warnings.length > 0 ? 'font-semibold text-amber-700 dark:text-amber-400' : 'font-medium'}>
                    {overviewQuery.data.warnings.length > 0 ? formatNumber(overviewQuery.data.warnings.length) : 'нет'}
                  </dd>
                </div>
                {backupsQuery.isSuccess ? (
                  <div className="flex items-center justify-between gap-3">
                    <dt className="text-slate-500 dark:text-slate-400">Последняя копия</dt>
                    <dd className={backupsQuery.data.latestBackup === null ? 'font-semibold text-red-700 dark:text-red-400' : 'font-medium'}>
                      {backupsQuery.data.latestBackup === null ? 'нет' : formatDateTime(backupsQuery.data.latestBackup.modifiedAt)}
                    </dd>
                  </div>
                ) : null}
              </dl>
            ) : null}
            <Link to="/admin/system" className="mt-3 inline-block text-sm font-medium text-emerald-700 hover:underline dark:text-emerald-400">
              Обзор системы →
            </Link>
          </Card>
        </div>
      </div>
    </div>
  )
}

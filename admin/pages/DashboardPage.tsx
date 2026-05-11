import { Suspense, lazy, useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useLocation } from 'react-router-dom'
import { apiRequest } from '../shared/api/client'
import { adminQueryKeys, queryOptions } from '../shared/api/query'
import { PageHeader, Card, Badge, ErrorState, Skeleton } from '../shared/ui'
import { useLeadsQuery } from '../entities/lead/api'

interface HealthPayload {
  status: string
  checks?: Record<string, unknown>
}

const LeadsStatusChart = lazy(() => import('../widgets/LeadsStatusChart'))

export default function DashboardPage() {
  const healthQuery = useQuery(queryOptions(
    adminQueryKeys.dashboard,
    () => apiRequest<HealthPayload>('/admin/api/system/health'),
  ))
  const leadsQuery = useLeadsQuery()
  const location = useLocation()
  const csrfEnabled = useMemo(() => {
    const csrfHeader = document.querySelector<HTMLMetaElement>('meta[name="admin-csrf-header"]')?.content
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="admin-csrf-token"]')?.content

    return (csrfHeader?.trim().length ?? 0) > 0 && (csrfToken?.trim().length ?? 0) > 0
  }, [])
  const routingReady = location.pathname.startsWith('/admin')

  return (
    <div>
      <PageHeader
        title="Dashboard"
        description="Production-ready foundation shell для модульной CMS админки."
      />
      <Card title="Состояние shell" description="Базовые подсистемы уже подключены.">
        <div className="flex flex-wrap gap-2">
          <Badge tone="success">Routing</Badge>
          <Badge tone="success">Error boundary</Badge>
          <Badge tone="success">Toasts</Badge>
          <Badge tone="success">Theme</Badge>
        </div>
      </Card>
      <Card className="mt-4" title="Быстрый статус">
        <dl className="space-y-3 text-sm">
          <div className="flex items-center justify-between">
            <dt className="text-slate-500 dark:text-slate-400">API-клиент</dt>
            <dd className={healthQuery.isSuccess ? 'font-semibold text-emerald-700 dark:text-emerald-400' : healthQuery.isError ? 'font-semibold text-red-700 dark:text-red-400' : 'font-semibold text-amber-700 dark:text-amber-400'}>
              {healthQuery.isSuccess ? 'ГОТОВ' : healthQuery.isError ? 'ОШИБКА' : 'ПРОВЕРКА'}
            </dd>
          </div>
          <div className="flex items-center justify-between">
            <dt className="text-slate-500 dark:text-slate-400">CSRF</dt>
            <dd className={csrfEnabled ? 'font-semibold text-emerald-700 dark:text-emerald-400' : 'font-semibold text-red-700 dark:text-red-400'}>
              {csrfEnabled ? 'ВКЛЮЧЕН' : 'ОТКЛЮЧЕН'}
            </dd>
          </div>
          <div className="flex items-center justify-between">
            <dt className="text-slate-500 dark:text-slate-400">SPA routing</dt>
            <dd className={routingReady ? 'font-semibold text-emerald-700 dark:text-emerald-400' : 'font-semibold text-amber-700 dark:text-amber-400'}>
              {routingReady ? 'ГОТОВ' : 'ПРОВЕРКА'}
            </dd>
          </div>
        </dl>
      </Card>
      <Card className="mt-4" title="System health через TanStack Query">
        {healthQuery.isPending ? <Skeleton className="h-5 w-48" /> : null}
        {healthQuery.isError ? (
          <ErrorState
            title="Не удалось загрузить состояние системы"
            description="Проверьте endpoint /admin/api/system/health и права доступа."
          />
        ) : null}
        {healthQuery.isSuccess ? (
          <p className="text-sm text-slate-600 dark:text-slate-300">
            Текущий статус: <strong>{healthQuery.data.status}</strong>
          </p>
        ) : null}
      </Card>
      <Card className="mt-4" title="Leads status (Recharts)">
        <Suspense fallback={<Skeleton className="h-40 w-full" />}>
          {leadsQuery.isPending ? <Skeleton className="h-40 w-full" /> : null}
          {leadsQuery.isError ? (
            <ErrorState
              title="Не удалось загрузить данные по лидам"
              description="Проверьте endpoint /admin/api/leads и права leads.view."
            />
          ) : null}
          {leadsQuery.isSuccess ? <LeadsStatusChart leads={leadsQuery.data.leads} statuses={leadsQuery.data.statuses} /> : null}
        </Suspense>
      </Card>
    </div>
  )
}

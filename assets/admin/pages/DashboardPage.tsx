import { Suspense, lazy } from 'react'
import { useQuery } from '@tanstack/react-query'
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
          {leadsQuery.data !== undefined ? (
            <LeadsStatusChart leads={leadsQuery.data.leads} />
          ) : (
            <p className="text-sm text-slate-500 dark:text-slate-400">Недостаточно данных для графика.</p>
          )}
        </Suspense>
      </Card>
    </div>
  )
}

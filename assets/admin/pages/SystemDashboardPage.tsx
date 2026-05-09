import { Badge, Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'
import { useSystemOverviewQuery } from '../entities/system/api'

export default function SystemDashboardPage() {
  const overviewQuery = useSystemOverviewQuery()

  if (overviewQuery.isPending) {
    return <PageLoadingState />
  }

  if (overviewQuery.isError || overviewQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить обзор системы"
        description="Проверьте endpoint /admin/api/system/overview и права system.view."
      />
    )
  }

  return (
    <div>
      <PageHeader title="Обзор системы" description="Сводный статус платформы, health-check и среда исполнения." />
      <Card title="Статус платформы">
        <div className="flex items-center gap-2">
          <Badge tone={overviewQuery.data.status === 'ok' ? 'success' : 'warning'}>
            {overviewQuery.data.status === 'ok' ? 'OK' : 'ERROR'}
          </Badge>
          <span className="text-sm text-slate-600 dark:text-slate-300">
            {overviewQuery.data.environment.appEnv} / PHP {overviewQuery.data.environment.phpVersion}
          </span>
        </div>
      </Card>
      <Card className="mt-4" title="Предупреждения">
        {overviewQuery.data.warnings.length === 0 ? (
          <p className="text-sm text-slate-500 dark:text-slate-400">Критичных предупреждений не найдено.</p>
        ) : (
          <ul className="space-y-2">
            {overviewQuery.data.warnings.map((warning) => (
              <li key={warning.code} className="text-sm">
                <strong>{warning.severity.toUpperCase()}</strong>: {warning.message}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  )
}

import { useSystemDatabaseQuery } from '../entities/system/api'
import { Badge, Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'

export default function SystemDatabasePage() {
  const databaseQuery = useSystemDatabaseQuery()

  if (databaseQuery.isPending) {
    return <PageLoadingState />
  }

  if (databaseQuery.isError || databaseQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить состояние БД"
        description="Проверьте endpoint /admin/api/system/database и права system.view."
      />
    )
  }

  return (
    <div>
      <PageHeader title="База данных" description="Диагностика подключения и версии PostgreSQL." />
      <Card title="Состояние подключения">
        <div className="flex items-center gap-2">
          <Badge tone={databaseQuery.data.connected ? 'success' : 'warning'}>
            {databaseQuery.data.connected ? 'Connected' : 'Disconnected'}
          </Badge>
          <span className="text-sm">{databaseQuery.data.databaseName}</span>
        </div>
        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">{databaseQuery.data.serverVersion}</p>
      </Card>
    </div>
  )
}

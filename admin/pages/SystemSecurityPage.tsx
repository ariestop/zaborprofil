import { useSystemSecurityQuery } from '../entities/system/api'
import { Badge, Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'

export default function SystemSecurityPage() {
  const securityQuery = useSystemSecurityQuery()

  if (securityQuery.isPending) {
    return <PageLoadingState />
  }

  if (securityQuery.isError || securityQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить security-аудит"
        description="Проверьте endpoint /admin/api/system/security и права system.view."
      />
    )
  }

  return (
    <div>
      <PageHeader title="Безопасность" description="Защита от подделки запросов и роли текущего пользователя." />
      <Card title="Текущий пользователь">
        <p className="text-sm">{securityQuery.data.actor.identifier ?? 'не определён'}</p>
        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{securityQuery.data.actor.roles.join(', ')}</p>
      </Card>
      <Card className="mt-4" title="Политика опасных действий">
        <div className="flex flex-wrap gap-2">
          <Badge tone={securityQuery.data.csrfRequired ? 'success' : 'warning'}>Проверка CSRF-токена</Badge>
          <Badge tone={securityQuery.data.originCheckRequired ? 'success' : 'warning'}>Проверка источника запроса</Badge>
          <Badge tone="warning">{securityQuery.data.dangerousActions.requiresRole}</Badge>
        </div>
      </Card>
    </div>
  )
}

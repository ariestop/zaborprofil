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
      <PageHeader title="Безопасность" description="Проверка CSRF/Origin политики и текущих ролей оператора." />
      <Card title="Текущий оператор">
        <p className="text-sm">{securityQuery.data.actor.identifier ?? 'anonymous'}</p>
        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{securityQuery.data.actor.roles.join(', ')}</p>
      </Card>
      <Card className="mt-4" title="Политика опасных действий">
        <div className="flex flex-wrap gap-2">
          <Badge tone={securityQuery.data.csrfRequired ? 'success' : 'warning'}>CSRF required</Badge>
          <Badge tone={securityQuery.data.originCheckRequired ? 'success' : 'warning'}>Origin required</Badge>
          <Badge tone="warning">{securityQuery.data.dangerousActions.requiresRole}</Badge>
        </div>
      </Card>
    </div>
  )
}

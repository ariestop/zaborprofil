import { useSystemAuditQuery } from '../entities/system/api'
import { Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'

export default function SystemAuditPage() {
  const auditQuery = useSystemAuditQuery(100)

  if (auditQuery.isPending) {
    return <PageLoadingState />
  }

  if (auditQuery.isError || auditQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить аудит действий"
        description="Проверьте endpoint /admin/api/system/audit и права system.view."
      />
    )
  }

  return (
    <div>
      <PageHeader title="Аудит действий админов" description="Последние записи системного и доменного audit log." />
      <Card title="Последние события">
        <div className="space-y-3">
          {auditQuery.data.map((entry) => (
            <div key={entry.id} className="rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-700">
              <p><strong>{entry.action}</strong> [{entry.entityType}]</p>
              <p className="text-slate-500 dark:text-slate-400">{entry.actorEmail ?? 'unknown'} · {entry.occurredAt}</p>
            </div>
          ))}
        </div>
      </Card>
    </div>
  )
}

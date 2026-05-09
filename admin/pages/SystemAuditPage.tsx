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
      <PageHeader title="Audit Log" description="Журнал последних действий в административной панели." />
      <Card title="Последние события">
        <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
          <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead className="bg-slate-50 text-left text-slate-600 dark:bg-slate-900 dark:text-slate-300">
              <tr>
                <th className="px-4 py-3 font-semibold">Time</th>
                <th className="px-4 py-3 font-semibold">Actor</th>
                <th className="px-4 py-3 font-semibold">Action</th>
                <th className="px-4 py-3 font-semibold">Entity</th>
                <th className="px-4 py-3 font-semibold">Diff</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {auditQuery.data.map((entry) => (
                <tr key={entry.id}>
                  <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{entry.occurredAt}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-200">{entry.actorEmail ?? 'system'}</td>
                  <td className="px-4 py-3 font-medium text-slate-950 dark:text-slate-100">{entry.action}</td>
                  <td className="px-4 py-3 text-slate-700 dark:text-slate-200">
                    {entry.entityType}
                    <br />
                    {entry.entityId}
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                    <pre>{JSON.stringify({ old: entry.oldValues, new: entry.newValues }, null, 2)}</pre>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  )
}

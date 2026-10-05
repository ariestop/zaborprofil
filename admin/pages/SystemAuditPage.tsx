import { useSystemAuditQuery } from '../entities/system/api'
import { Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'
import { formatDateTime } from '../shared/lib/format'
import { auditActionLabel, auditChanges, auditEntityLabel } from '../shared/lib/system-labels'

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
      <PageHeader title="Журнал действий" description="Кто и что менял в админке: последние 100 событий." />
      <Card title="Последние события">
        {auditQuery.data.length === 0 ? (
          <p className="text-sm text-graphite dark:text-slate-400">Событий пока нет.</p>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-line dark:border-slate-700">
            <table className="min-w-full divide-y divide-line text-sm dark:divide-slate-700">
              <thead className="bg-surface text-left text-graphite dark:bg-slate-900 dark:text-slate-300">
                <tr>
                  <th className="px-4 py-3 font-semibold">Когда</th>
                  <th className="px-4 py-3 font-semibold">Кто</th>
                  <th className="px-4 py-3 font-semibold">Действие</th>
                  <th className="px-4 py-3 font-semibold">Что изменилось</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-surface-strong dark:divide-slate-800">
                {auditQuery.data.map((entry) => {
                  const changes = auditChanges(entry.oldValues, entry.newValues)

                  return (
                    <tr key={entry.id} className="align-top">
                      <td className="whitespace-nowrap px-4 py-3 text-graphite dark:text-slate-400">{formatDateTime(entry.occurredAt)}</td>
                      <td className="px-4 py-3 text-ink dark:text-slate-200">{entry.actorEmail ?? 'Система'}</td>
                      <td className="px-4 py-3">
                        <span className="font-medium text-ink dark:text-slate-100">{auditActionLabel(entry.action)}</span>
                        <span className="block text-graphite dark:text-slate-400">{auditEntityLabel(entry.entityType)}</span>
                      </td>
                      <td className="px-4 py-3 text-ink dark:text-slate-200">
                        {changes.length === 0 ? (
                          <span className="text-graphite dark:text-slate-400">Без изменений полей</span>
                        ) : (
                          <ul className="grid gap-1">
                            {changes.map((change) => (
                              <li key={change.field} className="break-words">
                                <span className="font-mono text-xs text-graphite dark:text-slate-400">{change.field}</span>
                                {': '}
                                <span className="text-graphite line-through decoration-graphite dark:decoration-slate-400 dark:text-slate-400">{change.before}</span>
                                {' → '}
                                <span>{change.after}</span>
                              </li>
                            ))}
                          </ul>
                        )}
                        {entry.entityId !== null ? (
                          <span className="mt-1 block font-mono text-xs text-graphite dark:text-slate-400">ID {entry.entityId}</span>
                        ) : null}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  )
}

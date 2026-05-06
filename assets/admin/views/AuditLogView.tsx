import { useEffect, useState } from 'react'
import { apiRequest } from '../api/client'
import type { AuditLogEntryItem } from '../types/api'

export default function AuditLogView() {
  const [entries, setEntries] = useState<AuditLogEntryItem[]>([])
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const load = async (): Promise<void> => {
      try {
        setEntries(await apiRequest<AuditLogEntryItem[]>('/admin/api/system/audit'))
      } catch (exception) {
        setError(exception instanceof Error ? exception.message : 'Не удалось загрузить audit log.')
      }
    }
    void load()
  }, [])

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">System</p>
      <h2 className="mt-2 text-2xl font-bold text-slate-950">Audit Log</h2>

      {error && <p className="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{error}</p>}

      {!error && (
        <div className="mt-6 overflow-hidden rounded-xl border border-slate-200">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50 text-left text-slate-600">
              <tr>
                <th className="px-4 py-3 font-semibold">Time</th>
                <th className="px-4 py-3 font-semibold">Actor</th>
                <th className="px-4 py-3 font-semibold">Action</th>
                <th className="px-4 py-3 font-semibold">Entity</th>
                <th className="px-4 py-3 font-semibold">Diff</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {entries.map((entry) => (
                <tr key={entry.id}>
                  <td className="px-4 py-3 text-slate-500">{entry.occurredAt}</td>
                  <td className="px-4 py-3 text-slate-700">{entry.actorEmail ?? 'system'}</td>
                  <td className="px-4 py-3 font-medium text-slate-950">{entry.action}</td>
                  <td className="px-4 py-3 text-slate-700">{entry.entityType}<br />{entry.entityId}</td>
                  <td className="px-4 py-3 text-xs text-slate-500">
                    <pre>{JSON.stringify({ old: entry.oldValues, new: entry.newValues }, null, 2)}</pre>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}

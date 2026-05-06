import { useEffect, useState } from 'react'
import { apiRequest } from '../api/client'
import type { LeadItem } from '../types/api'

export default function LeadsView() {
  const [leads, setLeads] = useState<LeadItem[]>([])
  const [statuses, setStatuses] = useState<LeadItem['status'][]>(['new', 'in_progress', 'done', 'spam'])

  const loadLeads = async (): Promise<void> => {
    const response = await apiRequest<{ leads: LeadItem[], statuses?: LeadItem['status'][] }>('/admin/api/leads')
    setLeads(response.leads)
    setStatuses(response.statuses ?? ['new', 'in_progress', 'done', 'spam'])
  }

  const setStatus = async (lead: LeadItem, status: LeadItem['status']): Promise<void> => {
    await apiRequest<LeadItem>(`/admin/api/leads/${lead.id}/status`, {
      method: 'PATCH',
      body: { status },
    })
    await loadLeads()
  }

  useEffect(() => {
    void loadLeads()
  }, [])

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 className="text-lg font-semibold text-slate-950">Заявки</h2>
      <p className="mt-1 text-sm text-slate-600">Публичные формы отправляют заявки в `/api/leads` с consent snapshot.</p>

      <div className="mt-6 space-y-3">
        {leads.map((lead) => (
          <article key={lead.id} className="rounded-xl border border-slate-200 p-4">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="font-medium text-slate-900">{lead.name} · {lead.phone}</p>
                <p className="text-sm text-slate-500">{lead.email ?? 'email не указан'} · {lead.source}</p>
                {lead.message && <p className="mt-2 text-sm text-slate-700">{lead.message}</p>}
                {lead.spamScore > 0 && (
                  <p className="mt-2 text-xs text-red-700">
                    spam score {lead.spamScore} · {lead.spamReasons.join(', ')}
                  </p>
                )}
              </div>
              <select
                value={lead.status}
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                onChange={(event) => {
                  void setStatus(lead, event.target.value as LeadItem['status'])
                }}
              >
                {statuses.map((status) => (
                  <option key={status} value={status}>{status}</option>
                ))}
              </select>
            </div>
          </article>
        ))}
      </div>
    </section>
  )
}

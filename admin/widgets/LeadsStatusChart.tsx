import { useMemo } from 'react'
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import type { LeadItem } from '../types/api'

interface LeadsStatusChartProps {
  leads: LeadItem[]
  statuses?: LeadItem['status'][]
}

const LEAD_STATUS_LABELS: Record<LeadItem['status'], string> = {
  new: 'Новые',
  in_progress: 'В работе',
  done: 'Завершены',
  spam: 'Спам',
}

export default function LeadsStatusChart({ leads, statuses = [] }: LeadsStatusChartProps) {
  const data = useMemo(() => {
    const catalog = statuses.length > 0
      ? statuses
      : ['new', 'in_progress', 'done', 'spam']
    const counters = new Map<string, number>()

    for (const status of catalog) {
      counters.set(status, 0)
    }

    for (const lead of leads) {
      counters.set(lead.status, (counters.get(lead.status) ?? 0) + 1)
    }

    return Array.from(counters.entries()).map(([status, total]) => ({
      status,
      label: LEAD_STATUS_LABELS[status as LeadItem['status']] ?? status,
      total,
    }))
  }, [leads, statuses])

  return (
    <div className="h-56 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={data}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis dataKey="label" />
          <YAxis allowDecimals={false} />
          <Tooltip />
          <Bar dataKey="total" fill="#059669" radius={[6, 6, 0, 0]} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  )
}

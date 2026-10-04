import { useMemo } from 'react'
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { LEAD_STATUS_LABELS } from '../entities/lead/model'
import type { LeadStatus } from '../types/api'

interface LeadsStatusChartProps {
  byStatus: Record<string, number>
  statuses?: LeadStatus[]
}

export default function LeadsStatusChart({ byStatus, statuses = [] }: LeadsStatusChartProps) {
  const data = useMemo(() => {
    const catalog = statuses.length > 0 ? statuses : (Object.keys(LEAD_STATUS_LABELS) as LeadStatus[])

    return catalog.map((status) => ({
      status,
      label: LEAD_STATUS_LABELS[status] ?? status,
      total: byStatus[status] ?? 0,
    }))
  }, [byStatus, statuses])

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

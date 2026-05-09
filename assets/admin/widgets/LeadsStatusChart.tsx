import { useMemo } from 'react'
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import type { LeadItem } from '../types/api'

interface LeadsStatusChartProps {
  leads: LeadItem[]
}

export default function LeadsStatusChart({ leads }: LeadsStatusChartProps) {
  const data = useMemo(() => {
    const counters = new Map<string, number>()
    for (const lead of leads) {
      counters.set(lead.status, (counters.get(lead.status) ?? 0) + 1)
    }

    return Array.from(counters.entries()).map(([status, total]) => ({
      status,
      total,
    }))
  }, [leads])

  if (data.length === 0) {
    return <p className="text-sm text-slate-500 dark:text-slate-400">Нет данных для отображения.</p>
  }

  return (
    <div className="h-56 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={data}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis dataKey="status" />
          <YAxis allowDecimals={false} />
          <Tooltip />
          <Bar dataKey="total" fill="#059669" radius={[6, 6, 0, 0]} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  )
}

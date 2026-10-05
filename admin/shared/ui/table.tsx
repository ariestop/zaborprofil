import type { ReactNode } from 'react'

interface TableProps {
  head: ReactNode
  body: ReactNode
}

export function Table({ head, body }: TableProps) {
  return (
    <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
      <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
        <thead className="bg-slate-50 dark:bg-slate-900">{head}</thead>
        <tbody className="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-950">{body}</tbody>
      </table>
    </div>
  )
}

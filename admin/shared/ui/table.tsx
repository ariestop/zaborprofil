import type { ReactNode } from 'react'

interface TableProps {
  head: ReactNode
  body: ReactNode
}

export function Table({ head, body }: TableProps) {
  return (
    <div className="overflow-hidden rounded-xl border border-line dark:border-slate-800">
      <table className="min-w-full divide-y divide-line dark:divide-slate-800">
        <thead className="bg-surface dark:bg-slate-900">{head}</thead>
        <tbody className="divide-y divide-surface-strong bg-white dark:divide-slate-800 dark:bg-slate-950">{body}</tbody>
      </table>
    </div>
  )
}

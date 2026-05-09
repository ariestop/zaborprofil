import type { ReactNode } from 'react'
import { cn } from '../lib/cn'

interface BadgeProps {
  children: ReactNode
  tone?: 'neutral' | 'success' | 'warning'
}

export function Badge({ children, tone = 'neutral' }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
        tone === 'neutral' && 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        tone === 'success' && 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        tone === 'warning' && 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
      )}
    >
      {children}
    </span>
  )
}

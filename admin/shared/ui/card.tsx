import type { ReactNode } from 'react'
import { cn } from '../lib/cn'

interface CardProps {
  title?: string
  description?: string
  className?: string
  children: ReactNode
}

export function Card({ title, description, className, children }: CardProps) {
  return (
    <article className={cn('rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900', className)}>
      {title !== undefined ? <h3 className="text-base font-semibold">{title}</h3> : null}
      {description !== undefined ? <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p> : null}
      <div className={title !== undefined || description !== undefined ? 'mt-4' : ''}>{children}</div>
    </article>
  )
}

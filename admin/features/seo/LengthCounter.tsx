import { cn } from '../../shared/lib/cn'
import { evaluateLength } from './snippet'
import type { LengthLimits, LengthStatus } from './snippet'

const statusClasses: Record<LengthStatus, string> = {
  empty: 'text-slate-500 dark:text-slate-400',
  short: 'text-amber-700 dark:text-amber-400',
  good: 'text-brand-700 dark:text-brand-400',
  long: 'text-amber-700 dark:text-amber-400',
  'over-limit': 'text-red-700 dark:text-red-400',
}

interface LengthCounterProps {
  value: string
  limits: LengthLimits
  emptyHint?: string
  testId?: string
}

export function LengthCounter({ value, limits, emptyHint, testId }: LengthCounterProps) {
  const evaluation = evaluateLength(value, limits)
  const hint = evaluation.status === 'empty' && emptyHint !== undefined ? emptyHint : evaluation.hint

  return (
    <span
      className={cn('mt-1 block text-xs font-normal', statusClasses[evaluation.status])}
      data-status={evaluation.status}
      data-testid={testId}
    >
      {evaluation.length} / {limits.max} — {hint}
    </span>
  )
}

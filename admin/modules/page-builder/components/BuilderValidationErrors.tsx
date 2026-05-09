import type { BuilderValidationIssue } from '../types'

interface BuilderValidationErrorsProps {
  issues: BuilderValidationIssue[]
}

export function BuilderValidationErrors({ issues }: BuilderValidationErrorsProps) {
  if (issues.length === 0) {
    return null
  }

  return (
    <section className="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-900/10">
      <h3 className="text-sm font-semibold text-red-700 dark:text-red-300">Ошибки валидации</h3>
      <ul className="mt-2 space-y-1 text-xs text-red-700 dark:text-red-300">
        {issues.map((issue) => (
          <li key={`${issue.blockId}:${issue.path}`}>
            [{issue.blockId}] {issue.path}: {issue.message}
          </li>
        ))}
      </ul>
    </section>
  )
}

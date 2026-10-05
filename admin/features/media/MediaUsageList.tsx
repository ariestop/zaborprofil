import type { MediaUsageItem } from '../../types/api'
import { Badge } from '../../shared/ui'
import { usageTypeLabel } from './utils'

const STATUS_LABELS: Record<string, string> = {
  published: 'Опубликована',
  draft: 'Черновик',
  review: 'На проверке',
  approved: 'Одобрена',
  scheduled: 'Запланирована',
  unpublished: 'Снята с публикации',
  archived: 'В архиве',
  active: 'Активно',
  inactive: 'Неактивно',
}

export function MediaUsageList({ usages, className }: { usages: MediaUsageItem[]; className?: string }) {
  return (
    <ul className={className ?? 'space-y-1.5'} data-testid="media-usage-list">
      {usages.map((usage) => (
        <li key={`${usage.type}-${usage.sourceId}-${usage.location}`} className="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs dark:border-slate-700">
          <div className="flex flex-wrap items-center gap-1.5">
            {usage.adminPath !== null ? (
              <a href={usage.adminPath} target="_blank" rel="noreferrer" className="font-medium text-brand-700 underline dark:text-brand-400">
                {usage.title}
              </a>
            ) : (
              <span className="font-medium">{usage.title}</span>
            )}
            {usage.status !== null ? <Badge tone={usage.status === 'published' || usage.status === 'active' ? 'success' : 'neutral'}>{STATUS_LABELS[usage.status] ?? usage.status}</Badge> : null}
          </div>
          <p className="mt-0.5 text-slate-500 dark:text-slate-400">
            {usageTypeLabel(usage.type)} · {usage.location}
          </p>
        </li>
      ))}
    </ul>
  )
}

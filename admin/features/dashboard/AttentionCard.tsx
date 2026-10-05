import { Link } from 'react-router-dom'
import { cn } from '../../shared/lib/cn'
import { Skeleton } from '../../shared/ui'
import type { AttentionItem, AttentionTone } from './attention'
import { dashCard, dashHeading, dashMuted, dashOutlineButton } from './styles'

const markerTone: Record<AttentionTone, string> = {
  leads: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
  critical: 'bg-danger-50 text-danger dark:bg-red-900/40 dark:text-red-300',
  warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
}

interface AttentionCardProps {
  items: AttentionItem[]
  loading: boolean
  rebuilding: boolean
  onRebuild: () => void
}

/** «Требует внимания»: список по приоритету, у каждой строки цветной квадрат со счётчиком и одно действие. */
export function AttentionCard({ items, loading, rebuilding, onRebuild }: AttentionCardProps) {
  return (
    <section className={cn(dashCard, 'px-5 pb-2 pt-5')} aria-labelledby="dashboard-attention">
      <div className="flex items-baseline justify-between gap-3">
        <h2 id="dashboard-attention" className={dashHeading}>Требует внимания</h2>
        <span className={cn('text-[13px]', dashMuted)}>по приоритету</span>
      </div>

      {loading && items.length === 0 ? <Skeleton className="my-3 h-16 w-full" /> : null}
      {!loading && items.length === 0 ? (
        <p className="py-4 text-sm text-graphite dark:text-slate-300">Всё в порядке: новых заявок нет, сервер не сообщает о проблемах.</p>
      ) : null}

      {items.length > 0 ? (
        <ul className="mt-2 flex flex-col" aria-label="Задачи, требующие внимания">
          {items.map((item) => (
            <li key={item.id} className="flex items-center gap-3.5 border-t border-surface-strong py-3.5 dark:border-slate-800">
              <span
                aria-hidden="true"
                className={cn('flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-[10px] text-[15px] font-bold', markerTone[item.tone])}
              >
                {item.marker}
              </span>
              <div className="min-w-0 flex-1">
                <p className="m-0 font-semibold">{item.title}</p>
                <p className={cn('mt-0.5 text-[13px]', dashMuted)}>{item.description}</p>
              </div>
              {item.action === 'rebuild' ? (
                <button
                  type="button"
                  disabled={rebuilding}
                  onClick={onRebuild}
                  className={cn(dashOutlineButton, 'h-9 shrink-0 bg-transparent px-3 text-[13px] disabled:opacity-60')}
                >
                  {rebuilding ? 'Запускаю…' : item.actionLabel}
                </button>
              ) : (
                <Link to={item.href} className={cn(dashOutlineButton, 'h-9 shrink-0 px-3 text-[13px]')}>
                  {item.actionLabel}
                </Link>
              )}
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  )
}

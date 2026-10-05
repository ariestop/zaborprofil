import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import type { LeadDashboard } from '../../entities/lead/model'
import { cn } from '../../shared/lib/cn'
import { formatNumber } from '../../shared/lib/format'
import { Skeleton } from '../../shared/ui'
import type { ObservabilityTile, ObservabilityTone } from './observability'
import { buildDailyBars, CHART_HEIGHT, shortDate, type PublicationCount, type SiteStateRow, type SiteTone } from './summary'
import { dashCard, dashHeading, dashMuted } from './styles'

const kpiBox = cn(dashCard, 'block p-4 text-inherit no-underline transition hover:border-[#A6D8C4] dark:hover:border-emerald-800')

interface KpiProps {
  label: string
  to?: string
  children: ReactNode
}

function Kpi({ label, to, children }: KpiProps) {
  const content = (
    <>
      <p className={cn('m-0 text-[13px]', dashMuted)}>{label}</p>
      <p className="mt-1 text-[28px] font-bold leading-[1.45]">{children}</p>
    </>
  )

  return to === undefined ? <div className={cn(dashCard, 'p-4')}>{content}</div> : <Link to={to} className={kpiBox}>{content}</Link>
}

export interface KpiGridProps {
  newCount: number | undefined
  inProgress: number | undefined
  publications: PublicationCount | undefined
  doneLastWeek: number | undefined
  canViewLeads: boolean
  canViewPages: boolean
}

const number = (value: number | undefined) => (value === undefined ? '—' : formatNumber(value))

/** Четыре плитки: новые, в работе, опубликовано, завершено за неделю. */
export function KpiGrid({ newCount, inProgress, publications, doneLastWeek, canViewLeads, canViewPages }: KpiGridProps) {
  return (
    <div className="grid grid-cols-2 gap-3">
      {canViewLeads ? (
        <>
          <Kpi label="Новые" to="/admin/crm?status=new">
            <span className={newCount !== undefined && newCount > 0 ? 'text-[#9A3412] dark:text-orange-400' : undefined}>{number(newCount)}</span>
          </Kpi>
          <Kpi label="В работе" to="/admin/crm?status=in_progress">{number(inProgress)}</Kpi>
        </>
      ) : null}
      {canViewPages ? (
        <Kpi label="Опубликовано" to="/admin/pages">
          {publications === undefined ? '—' : formatNumber(publications.published)}
          {publications === undefined ? null : <span className={cn('text-[15px] font-medium', dashMuted)}> / {formatNumber(publications.total)}</span>}
        </Kpi>
      ) : null}
      {canViewLeads ? <Kpi label="Завершено за неделю" to="/admin/crm?status=done">{number(doneLastWeek)}</Kpi> : null}
    </div>
  )
}

const tileTone: Record<ObservabilityTone, string> = {
  ok: 'text-[#101828] dark:text-slate-100',
  warning: 'text-[#9A3412] dark:text-orange-400',
  critical: 'text-[#B42318] dark:text-red-400',
}

interface MonitoringCardProps {
  tiles: ObservabilityTile[]
}

/** Сжатый блок мониторинга: 5xx, очередь задач и диск — три строки по одному делу вместо трёх крупных плиток. */
export function MonitoringCard({ tiles }: MonitoringCardProps) {
  return (
    <section className={cn(dashCard, 'overflow-hidden')} aria-label="Мониторинг сервера">
      <ul className="m-0 flex list-none flex-col p-0">
        {tiles.map((tile) => (
          <li key={tile.id} className="border-t border-[#F0F2F5] first:border-t-0 dark:border-slate-800">
            <Link
              to={tile.href}
              className="flex items-center justify-between gap-3 px-4 py-2 text-inherit no-underline transition hover:bg-slate-50 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500 dark:hover:bg-slate-800/60"
            >
              <span className="min-w-0">
                <span className="block truncate text-[13px] text-[#344054] dark:text-slate-300">{tile.label}</span>
                <span className={cn('block truncate text-xs', dashMuted)}>{tile.hint}</span>
              </span>
              <span className={cn('shrink-0 text-lg font-bold', tileTone[tile.tone])}>{tile.value}</span>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  )
}

interface LeadsChartCardProps {
  data: LeadDashboard | undefined
  loading: boolean
  error: boolean
}

/** «Заявки за 14 дней»: столбики по дням, последний (сегодня) — акцентным цветом. */
export function LeadsChartCard({ data, loading, error }: LeadsChartCardProps) {
  const bars = data === undefined ? [] : buildDailyBars(data.daily)
  const today = bars.at(-1)
  const first = bars[0]

  return (
    <section className={cn(dashCard, 'p-5')} aria-labelledby="dashboard-chart">
      <div className="flex items-baseline justify-between">
        <h2 id="dashboard-chart" className={dashHeading}>Заявки за 14 дней</h2>
        {data !== undefined ? <span className="text-[22px] font-bold">{formatNumber(data.createdLast14Days)}</span> : null}
      </div>

      {loading ? <Skeleton className="mt-3.5 h-[110px] w-full" /> : null}
      {error ? <p className={cn('mt-3 text-sm', dashMuted)}>Не удалось загрузить данные по заявкам.</p> : null}

      {data !== undefined ? (
        <>
          <div
            role="img"
            aria-label={`Столбчатая диаграмма заявок по дням за ${bars.length} дней, сегодня ${today?.count ?? 0}`}
            className="mt-3.5 flex items-end gap-[5px] border-b border-[#E4E7EC] dark:border-slate-700"
            style={{ height: CHART_HEIGHT }}
          >
            {bars.map((bar) => (
              <div
                key={bar.date}
                title={`${shortDate(bar.date)}: ${bar.count}`}
                className={cn('flex-1 rounded-t-[4px]', bar.today ? 'bg-[#047857]' : 'bg-[#047857]/25')}
                style={{ height: bar.height }}
              />
            ))}
          </div>
          <div className={cn('mt-1.5 flex justify-between text-xs', dashMuted)}>
            <span>{first === undefined ? '' : shortDate(first.date)}</span>
            <span>сегодня</span>
          </div>
        </>
      ) : null}
    </section>
  )
}

const dotTone: Record<SiteTone, string> = {
  ok: 'rounded-full bg-[#16A34A]',
  warning: 'rounded-[2px] bg-[#DC8A05]',
  critical: 'rounded-[2px] bg-[#B42318]',
  neutral: 'rounded-full bg-[#98A2B3]',
}

const valueTone: Record<SiteTone, string> = {
  ok: 'text-[#166534] dark:text-emerald-400',
  warning: 'text-[#7A4A00] dark:text-amber-300',
  critical: 'text-[#B42318] dark:text-red-400',
  neutral: 'text-[#101828] dark:text-slate-100',
}

interface SiteStateCardProps {
  rows: SiteStateRow[]
  loading: boolean
}

/** «Состояние сайта»: цветной маркер + короткий вердикт по каждой подсистеме (круг — норма, квадрат — проблема). */
export function SiteStateCard({ rows, loading }: SiteStateCardProps) {
  return (
    <section className={cn(dashCard, 'p-5')} aria-labelledby="dashboard-site-state">
      <h2 id="dashboard-site-state" className={dashHeading}>Состояние сайта</h2>
      {loading && rows.length === 0 ? <Skeleton className="mt-3 h-24 w-full" /> : null}
      <dl className="m-0 mt-3 flex flex-col text-[13px]">
        {rows.map((row) => (
          <div key={row.id} className="flex items-center gap-2.5 border-t border-[#F0F2F5] py-2.5 dark:border-slate-800">
            <span aria-hidden="true" className={cn('h-2 w-2 shrink-0', dotTone[row.tone])} />
            <dt className="flex-1 text-[#344054] dark:text-slate-300">{row.label}</dt>
            <dd className={cn('m-0 font-semibold', valueTone[row.tone])}>{row.value}</dd>
          </div>
        ))}
      </dl>
    </section>
  )
}

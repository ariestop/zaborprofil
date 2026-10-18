import { useId, useState } from 'react'
import { usePortfolioQuery, type PortfolioProjectSummary } from '../../entities/portfolio/api'
import { Button, Input } from '../../shared/ui'
import { cn } from '../../shared/lib/cn'
import { moveMedia, PORTFOLIO_BLOCK_MAX, portfolioBlockSettings, projectsForBlock, sortedCategories } from './portfolio-utils'

const selectClass =
  'h-12 w-full rounded-lg border border-line-strong bg-white px-3 text-sm text-ink outline-hidden ring-brand-500 focus:ring-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100'

const iconButton =
  'flex size-10 shrink-0 items-center justify-center rounded-lg text-graphite transition hover:bg-surface-strong hover:text-ink focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 disabled:pointer-events-none disabled:opacity-30 dark:hover:bg-slate-800'

function Arrow({ d }: { d: string }) {
  return (
    <svg width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.9} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d={d} />
    </svg>
  )
}

interface PortfolioBlockFieldProps {
  content: Record<string, unknown>
  onChange: (patch: Record<string, unknown>) => void
}

/**
 * Поле блока «Работы из портфолио»: выбрать конкретные работы (порядок — стрелками) или показать новые работы раздела.
 * На сайте выводятся только опубликованные работы.
 */
export function PortfolioBlockField({ content, onChange }: PortfolioBlockFieldProps) {
  const query = usePortfolioQuery()
  const settings = portfolioBlockSettings(content)
  const [mode, setMode] = useState<'selected' | 'latest'>(settings.projectIds.length > 0 ? 'selected' : 'latest')
  const ids = { add: useId(), category: useId(), limit: useId() }

  if (query.isPending) {
    return <p className="text-sm text-graphite">Загружаем работы портфолио…</p>
  }
  if (query.isError) {
    return (
      <p className="rounded-[10px] bg-surface p-3 text-sm text-graphite dark:bg-slate-800/60 dark:text-slate-300">
        Не удалось загрузить портфолио. Для выбора работ нужен доступ к разделу «Портфолио».
      </p>
    )
  }

  const categories = sortedCategories(query.data.categories)
  const published = query.data.projects.filter((project) => project.status === 'published')
  const byId = new Map(query.data.projects.map((project) => [project.id, project]))
  const chosen = settings.projectIds.map((id) => byId.get(id)).filter((project): project is PortfolioProjectSummary => project !== undefined)
  const available = published.filter((project) => !settings.projectIds.includes(project.id))
  const shown = projectsForBlock(query.data, settings)

  const setIds = (next: string[]): void => onChange({ projectIds: next })

  return (
    <div className="flex flex-col gap-3" data-testid="portfolio-block-field">
      <div role="radiogroup" aria-label="Какие работы показать" className="grid gap-2 sm:grid-cols-2">
        {([
          ['selected', 'Выбрать работы', 'Вы сами выбираете работы и их порядок'],
          ['latest', 'Новые работы', 'Последние опубликованные, из всех или из одного раздела'],
        ] as const).map(([value, label, hint]) => (
          <button
            key={value}
            type="button"
            role="radio"
            aria-checked={mode === value}
            onClick={() => {
              setMode(value)
              if (value === 'latest' && settings.projectIds.length > 0) {
                setIds([])
              }
            }}
            className={cn(
              'flex min-h-12 flex-col items-start rounded-[10px] border px-3 py-2 text-left transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500',
              mode === value ? 'border-brand-600 bg-brand-50 dark:bg-brand-950/30' : 'border-line-strong hover:border-graphite dark:border-slate-700',
            )}
          >
            <span className="text-sm font-semibold">{label}</span>
            <span className="text-[13px] text-graphite dark:text-slate-400">{hint}</span>
          </button>
        ))}
      </div>

      {mode === 'selected' ? (
        <>
          {chosen.length === 0 ? (
            <p className="text-sm text-graphite dark:text-slate-400">Работы не выбраны — добавьте их из списка ниже.</p>
          ) : (
            <ol className="flex flex-col gap-1.5" aria-label="Выбранные работы">
              {chosen.map((project, index) => (
                <li key={project.id} className="flex items-center gap-2 rounded-[10px] border border-line p-1.5 dark:border-slate-700">
                  <span className="aspect-4/3 w-14 shrink-0 overflow-hidden rounded-md bg-surface-strong dark:bg-slate-800">
                    {project.cover !== null ? <img src={project.cover} alt="" className="size-full object-cover" /> : null}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-semibold">{project.title}</span>
                    <span className="block truncate text-[13px] text-graphite dark:text-slate-400">
                      {categories.find((category) => category.id === project.categoryId)?.title ?? ''}
                      {project.status !== 'published' ? ' · черновик, на сайте не видна' : ''}
                    </span>
                  </span>
                  <button type="button" className={iconButton} aria-label={`Выше: ${project.title}`} disabled={index === 0} onClick={() => setIds(moveMedia(settings.projectIds, index, index - 1))}>
                    <Arrow d="M6 14.5l6-6 6 6" />
                  </button>
                  <button type="button" className={iconButton} aria-label={`Ниже: ${project.title}`} disabled={index === chosen.length - 1} onClick={() => setIds(moveMedia(settings.projectIds, index, index + 1))}>
                    <Arrow d="M6 9.5l6 6 6-6" />
                  </button>
                  <button type="button" className={cn(iconButton, 'hover:text-danger')} aria-label={`Убрать: ${project.title}`} onClick={() => setIds(settings.projectIds.filter((id) => id !== project.id))}>
                    <Arrow d="M6.5 6.5l11 11M17.5 6.5l-11 11" />
                  </button>
                </li>
              ))}
            </ol>
          )}
          <div>
            <label htmlFor={ids.add} className="mb-1.5 block text-sm font-semibold">Добавить работу</label>
            <select
              id={ids.add}
              className={selectClass}
              value=""
              disabled={available.length === 0 || settings.projectIds.length >= PORTFOLIO_BLOCK_MAX}
              onChange={(event) => {
                if (event.target.value !== '') {
                  setIds([...settings.projectIds, event.target.value])
                }
              }}
            >
              <option value="">{available.length === 0 ? 'Все опубликованные работы уже выбраны' : 'Выберите работу…'}</option>
              {categories.map((category) => {
                const options = available.filter((project) => project.categoryId === category.id)
                return options.length === 0 ? null : (
                  <optgroup key={category.id} label={category.title}>
                    {options.map((project) => (
                      <option key={project.id} value={project.id}>{project.title}</option>
                    ))}
                  </optgroup>
                )
              })}
            </select>
          </div>
        </>
      ) : (
        <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_120px]">
          <div>
            <label htmlFor={ids.category} className="mb-1.5 block text-sm font-semibold">Раздел</label>
            <select id={ids.category} className={selectClass} value={settings.category} onChange={(event) => onChange({ category: event.target.value })}>
              <option value="">Все разделы</option>
              {categories.map((category) => (
                <option key={category.id} value={category.slug}>{category.title}</option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor={ids.limit} className="mb-1.5 block text-sm font-semibold">Сколько</label>
            <Input
              id={ids.limit}
              type="number"
              min={1}
              max={PORTFOLIO_BLOCK_MAX}
              value={String(settings.limit)}
              onChange={(event) => onChange({ limit: Math.min(PORTFOLIO_BLOCK_MAX, Math.max(1, Number.parseInt(event.target.value, 10) || 1)) })}
            />
          </div>
        </div>
      )}

      <p className="rounded-[10px] bg-surface p-3 text-[13px] text-graphite dark:bg-slate-800/60 dark:text-slate-300">
        {shown.length === 0
          ? 'Сейчас блок пустой и на сайт не выводится: нет опубликованных работ по этим условиям.'
          : `На сайте: ${shown.length} ${shown.length === 1 ? 'работа' : shown.length < 5 ? 'работы' : 'работ'} в горизонтальный ряд, кнопка «Все работы» ведёт в портфолио.`}
        {published.length === 0 ? ' Опубликуйте работы в разделе «Портфолио».' : ''}
      </p>
      {mode === 'selected' && chosen.length > 0 ? (
        <Button type="button" variant="ghost" size="sm" className="self-start" onClick={() => setIds([])}>
          Очистить выбор
        </Button>
      ) : null}
    </div>
  )
}

/** Предпросмотр блока в редакторе: те же работы, что увидит посетитель, рядом с прокруткой. */
export function PortfolioBlockPreview({ content }: { content: Record<string, unknown> }) {
  const query = usePortfolioQuery()
  const shown = query.data === undefined ? [] : projectsForBlock(query.data, portfolioBlockSettings(content))
  const title = typeof content.title === 'string' && content.title.trim() !== '' ? content.title : 'Наши работы'

  if (query.isSuccess && shown.length === 0) {
    return (
      <span className="m-6 flex flex-col items-center gap-1 rounded-xl border-[1.5px] border-dashed border-line-strong px-4 py-7 text-center text-graphite">
        <span className="font-semibold">Работы не выбраны</span>
        <span className="text-[13px]">Видно только в редакторе: пустой блок на сайт не выводится</span>
      </span>
    )
  }

  return (
    <span className="block px-8 py-7">
      <span className="block text-xs font-extrabold tracking-[0.1em] text-brand-700 uppercase">Портфолио</span>
      <span className="mt-1 mb-5 flex items-end justify-between gap-3">
        <span className="text-2xl font-semibold text-ink dark:text-slate-950">{title}</span>
        <span className="text-sm font-semibold whitespace-nowrap text-brand-700">Все работы →</span>
      </span>
      <span className="flex gap-3 overflow-hidden">
        {(query.isPending ? [null, null, null] : shown).map((project, index) => (
          <span key={project?.id ?? index} className="block w-[31%] shrink-0 overflow-hidden rounded-xl border border-line dark:border-slate-200">
            {project?.cover ? <img src={project.cover} alt="" className="aspect-4/3 w-full object-cover" /> : <span className="block aspect-4/3 bg-line dark:bg-slate-200" />}
            <span className="block p-3 text-sm font-semibold text-ink dark:text-slate-950">{project?.title ?? ' '}</span>
          </span>
        ))}
      </span>
    </span>
  )
}

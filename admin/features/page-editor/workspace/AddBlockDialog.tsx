import { useState, type ReactNode } from 'react'
import type { BuilderBlockType } from '../../../modules/page-builder/types'
import { NavIcon } from '../../../layouts/nav-icons'
import { cn } from '../../../shared/lib/cn'
import { Dialog } from '../../../shared/ui'
import { CATALOG_KINDS, KIND_CATEGORIES, type BlockKind, type BlockKindCategory } from './block-kinds'

interface AddBlockDialogProps {
  open: boolean
  /** Подпись, куда встанет блок: «после блока «Цены»» или «в конец страницы». */
  placement: string
  onOpenChange: (open: boolean) => void
  onAdd: (type: BuilderBlockType) => void
  onInsertSection?: () => void
}

const bar = (width: string, height: number, color: string) => <span className="block rounded-[3px]" style={{ width, height, background: color }} />

/** Схематичная миниатюра блока в каталоге. */
function Thumb({ kind }: { kind: BlockKind }): ReactNode {
  switch (kind.icon) {
    case 'hero':
      return <span className="flex h-full flex-col justify-center gap-1.5 rounded-md bg-ink px-3">{bar('70%', 8, 'white')}{bar('48%', 6, 'var(--color-line-strong)')}<span className="mt-1 block h-3 w-10 rounded bg-white" /></span>
    case 'slider':
      return <span className="flex h-full items-end justify-center gap-1 rounded-md bg-graphite pb-2">{[0, 1, 2].map((dot) => <span key={dot} className={cn('h-1.5 w-1.5 rounded-full', dot === 0 ? 'bg-white' : 'bg-line-strong')} />)}</span>
    case 'text':
      return <span className="flex flex-col gap-1.5 p-1">{bar('55%', 8, 'var(--color-graphite)')}{bar('100%', 5, 'var(--color-line-strong)')}{bar('94%', 5, 'var(--color-line-strong)')}{bar('100%', 5, 'var(--color-line-strong)')}{bar('66%', 5, 'var(--color-line-strong)')}</span>
    case 'faq':
      return <span className="flex flex-col gap-1.5">{[60, 48, 56].map((width) => <span key={width} className="flex h-[18px] items-center justify-between rounded bg-white px-2">{bar(`${width}%`, 5, 'var(--color-graphite)')}<span className="h-1.5 w-1.5 rounded-sm bg-graphite/60" /></span>)}</span>
    case 'features':
      return <span className="grid h-full grid-cols-3 gap-1.5">{[0, 1, 2].map((card) => <span key={card} className="flex flex-col gap-1 rounded bg-white p-1.5">{bar('70%', 5, 'var(--color-graphite)')}{bar('100%', 4, 'var(--color-line-strong)')}{bar('80%', 4, 'var(--color-line-strong)')}</span>)}</span>
    case 'steps':
      return <span className="flex h-full items-center px-1">{[0, 1, 2, 3].map((step) => <span key={step} className="flex flex-1 items-center last:flex-none"><span className="h-[18px] w-[18px] shrink-0 rounded-full bg-brand-700" />{step < 3 ? <span className="h-0.5 flex-1 bg-brand-200" /> : null}</span>)}</span>
    case 'gallery':
      return <span className="grid h-full grid-cols-3 grid-rows-2 gap-1">{[0, 1, 2, 3, 4, 5].map((tile) => <span key={tile} className={cn('rounded', tile % 2 === 0 ? 'bg-line-strong' : 'bg-graphite/60')} />)}</span>
    case 'portfolio':
      return <span className="grid h-full grid-cols-3 gap-1.5">{[0, 1, 2].map((card) => <span key={card} className="flex flex-col gap-1"><span className="flex-1 rounded bg-graphite/60" />{bar('76%', 5, 'var(--color-graphite)')}</span>)}</span>
    case 'prices':
      return <span className="flex h-full flex-col overflow-hidden rounded bg-white"><span className="flex gap-1.5 bg-line px-2 py-1.5">{bar('66%', 5, 'var(--color-graphite)')}{bar('34%', 5, 'var(--color-graphite)')}</span>{[0, 1, 2].map((row) => <span key={row} className="flex gap-1.5 border-t border-surface-strong px-2 py-[7px]">{bar('66%', 4, 'var(--color-line-strong)')}{bar('34%', 4, 'var(--color-graphite)')}</span>)}</span>
    case 'cta':
      return <span className="flex h-full items-center justify-between gap-2 rounded-md bg-brand-700 px-3"><span className="flex flex-1 flex-col gap-1">{bar('80%', 7, 'white')}{bar('56%', 5, 'var(--color-brand-200)')}</span><span className="h-3.5 w-9 rounded bg-white" /></span>
    case 'form':
      return <span className="flex h-full flex-col justify-center gap-1.5 rounded-md bg-white px-2.5">{bar('50%', 7, 'var(--color-graphite)')}<span className="flex gap-1"><span className="h-3.5 flex-1 rounded border border-line-strong" /><span className="h-3.5 flex-1 rounded border border-line-strong" /><span className="h-4 w-7 rounded bg-ink" /></span></span>
    default:
      return null
  }
}

/** Каталог блоков: поиск, группы и карточки с миниатюрами. Блок вставляется в выбранное место страницы. */
export function AddBlockDialog({ open, placement, onOpenChange, onAdd, onInsertSection }: AddBlockDialogProps) {
  const [query, setQuery] = useState('')
  const [category, setCategory] = useState<BlockKindCategory | 'all'>('all')
  const needle = query.trim().toLowerCase()
  const kinds = CATALOG_KINDS
    .filter((kind) => category === 'all' || kind.category === category)
    .filter((kind) => needle === '' || `${kind.name} ${kind.description}`.toLowerCase().includes(needle))

  const close = (next: boolean) => {
    if (!next) {
      setQuery('')
      setCategory('all')
    }
    onOpenChange(next)
  }

  return (
    <Dialog open={open} onOpenChange={close} title="Добавить блок" description={placement} contentClassName="max-w-[880px]">
      <div className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center gap-2.5">
          <label className="flex h-10 min-w-56 flex-1 items-center gap-2 rounded-[10px] border border-line-strong px-3 text-graphite focus-within:ring-2 focus-within:ring-brand-500 dark:border-slate-700">
            <NavIcon name="search" size={16} />
            <input
              type="search"
              aria-label="Найти блок"
              placeholder="Найти блок: цены, фото, форма…"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              className="min-w-0 flex-1 border-0 bg-transparent text-sm text-ink outline-hidden dark:text-slate-100"
            />
          </label>
          <div role="group" aria-label="Группы блоков" className="flex flex-wrap gap-1.5">
            {KIND_CATEGORIES.map((item) => (
              <button
                key={item.id}
                type="button"
                aria-pressed={category === item.id}
                onClick={() => setCategory(item.id)}
                className={cn(
                  'h-8 rounded-full border px-3 text-[13px] transition',
                  category === item.id
                    ? 'border-ink bg-ink font-semibold text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-900'
                    : 'border-line-strong bg-white font-medium text-graphite hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
                )}
              >
                {item.label}
              </button>
            ))}
          </div>
        </div>

        {kinds.length === 0 ? (
          <p className="text-sm text-graphite">Ничего не нашлось. Попробуйте другое слово или группу «Все».</p>
        ) : (
          <ul className="-m-0.5 grid max-h-[min(560px,calc(88vh-230px))] gap-3 overflow-y-auto p-0.5" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(min(230px, 100%), 1fr))' }}>
            {kinds.map((kind) => (
              <li key={kind.type}>
                <button
                  type="button"
                  onClick={() => onAdd(kind.type)}
                  className="flex h-full w-full flex-col gap-2.5 rounded-xl border border-line bg-white p-2.5 text-left transition hover:border-brand-200 hover:bg-brand-50 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-slate-700 dark:bg-slate-900"
                >
                  <span aria-hidden="true" className="block h-24 overflow-hidden rounded-[9px] bg-surface-strong p-3 dark:bg-slate-800"><Thumb kind={kind} /></span>
                  <span className="flex flex-col gap-0.5 px-1 pb-1">
                    <span className="flex items-center gap-1.5">
                      <span className="font-semibold">{kind.name}</span>
                      {kind.often ? <span className="rounded-[5px] bg-brand-50 px-1.5 text-[11px] font-semibold text-brand-800">часто</span> : null}
                    </span>
                    <span className="text-[13px] text-graphite dark:text-slate-400">{kind.description}</span>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-surface-strong pt-3 dark:border-slate-800">
          <p className="text-[13px] text-graphite dark:text-slate-400">В каталоге только блоки с готовым оформлением на сайте.</p>
          {onInsertSection !== undefined ? (
            <button type="button" onClick={onInsertSection} className="text-[13px] font-semibold text-brand-700 hover:underline dark:text-brand-400">
              Вставить секцию из шаблона
            </button>
          ) : null}
        </div>
      </div>
    </Dialog>
  )
}

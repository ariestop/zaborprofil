import type { ReactNode } from 'react'
import type { BuilderBlock } from '../../../modules/page-builder/types'
import { NavIcon } from '../../../layouts/nav-icons'
import { cn } from '../../../shared/lib/cn'
import { BlockPreview, LeadFormPreview } from './BlockPreview'
import { blockTitle, canonicalType, isPlaceholderBlock } from './block-kinds'

export type PreviewDevice = 'desktop' | 'phone'

interface PageCanvasProps {
  blocks: BuilderBlock[]
  selectedBlockId: string | null
  pageH1: string
  pagePath: string
  device: PreviewDevice
  onDeviceChange: (device: PreviewDevice) => void
  onSelect: (blockId: string) => void
  onMove: (blockId: string, direction: -1 | 1) => void
  onDuplicate: (blockId: string) => void
  onToggleHidden: (blockId: string) => void
  onDelete: (blockId: string) => void
  onInsertAfter: (blockId: string) => void
}

const toolButton = 'inline-flex h-7 w-[30px] items-center justify-center rounded-md bg-white/15 text-white transition hover:bg-white/25 disabled:opacity-40'

function ToolIcon({ children }: { children: ReactNode }) {
  return (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{children}</svg>
  )
}

/** Центральная колонка: страница так, как её увидит посетитель; клик по секции открывает её поля. */
export function PageCanvas({
  blocks,
  selectedBlockId,
  pageH1,
  pagePath,
  device,
  onDeviceChange,
  onSelect,
  onMove,
  onDuplicate,
  onToggleHidden,
  onDelete,
  onInsertAfter,
}: PageCanvasProps) {
  const hasInlineForm = blocks.some((block) => block.enabled && (canonicalType(block.type) === 'contact-form' || block.type === 'cta_form'))

  return (
    <section aria-label="Предпросмотр страницы" className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-[13px] text-graphite dark:text-slate-400">Как на сайте · клик по секции открывает её поля</p>
        <div role="group" aria-label="Ширина предпросмотра" className="flex rounded-[10px] bg-line p-[3px] dark:bg-slate-800">
          {([['desktop', 'Как на компьютере', <path key="d" d="M3.5 5.5h17v11h-17zM9 20h6M12 16.5V20" />], ['phone', 'Как на телефоне', <path key="p" d="M7.5 3.5h9v17h-9zM11 17.5h2" />]] as const).map(([value, label, icon]) => (
            <button
              key={value}
              type="button"
              aria-label={label}
              aria-pressed={device === value}
              onClick={() => onDeviceChange(value)}
              className={cn('flex h-[34px] w-10 items-center justify-center rounded-lg', device === value ? 'bg-white text-ink shadow-xs dark:bg-slate-900 dark:text-slate-100' : 'text-graphite dark:text-slate-400')}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{icon}</svg>
            </button>
          ))}
        </div>
      </div>

      <div className={cn('mx-auto w-full transition-[max-width]', device === 'phone' ? 'max-w-[390px]' : 'max-w-none')}>
        <div className="flex items-center gap-2.5 rounded-t-xl border border-b-0 border-line-strong bg-surface px-3.5 py-2 text-xs text-graphite dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
          <span className="truncate font-mono">zaborprofil.ru{pagePath}</span>
        </div>
        <div className="overflow-hidden rounded-b-xl border border-line-strong bg-white text-ink dark:border-slate-700">
          <div className="px-8 pb-2 pt-8">
            {/* В админке заголовок страницы — в шапке редактора; здесь только его вид на сайте, без второго h1. */}
            <p className="text-3xl font-bold tracking-tight text-slate-950">{pageH1}</p>
          </div>
          {blocks.map((block, index) => {
            const selected = block.id === selectedBlockId
            const title = blockTitle(block)

            return (
              <div key={block.id} className={cn('relative', selected && 'z-[1] outline outline-2 -outline-offset-2 outline-brand-700')} data-testid="canvas-block">
                {selected ? (
                  <div className="flex flex-wrap items-center gap-1.5 bg-brand-700 py-1.5 pl-3 pr-2 text-[13px] text-white">
                    <span className="min-w-28 flex-1 font-semibold">{title}</span>
                    <button type="button" className={toolButton} aria-label="Переместить выше" disabled={index === 0} onClick={() => onMove(block.id, -1)}>
                      <ToolIcon><path d="M12 19.5v-15M7 9.5l5-5 5 5" /></ToolIcon>
                    </button>
                    <button type="button" className={toolButton} aria-label="Переместить ниже" disabled={index === blocks.length - 1} onClick={() => onMove(block.id, 1)}>
                      <ToolIcon><path d="M12 4.5v15M7 14.5l5 5 5-5" /></ToolIcon>
                    </button>
                    <button type="button" className={toolButton} aria-label="Дублировать блок" onClick={() => onDuplicate(block.id)}>
                      <ToolIcon><rect x="8.5" y="8.5" width="11" height="11" rx="2" /><path d="M15.5 8.5V6a1.5 1.5 0 0 0-1.5-1.5H6A1.5 1.5 0 0 0 4.5 6v8A1.5 1.5 0 0 0 6 15.5h2.5" /></ToolIcon>
                    </button>
                    <button type="button" className={toolButton} aria-label={block.enabled ? 'Скрыть блок на сайте' : 'Показать блок на сайте'} onClick={() => onToggleHidden(block.id)}>
                      <ToolIcon><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" /><circle cx="12" cy="12" r="2.8" /></ToolIcon>
                    </button>
                    <button type="button" className={toolButton} aria-label="Удалить блок" onClick={() => onDelete(block.id)}>
                      <ToolIcon><path d="M5 7h14M10 11v6M14 11v6M6.5 7l1 12a1.5 1.5 0 0 0 1.5 1.4h6a1.5 1.5 0 0 0 1.5-1.4l1-12M9.5 7V4.5h5V7" /></ToolIcon>
                    </button>
                  </div>
                ) : null}
                {isPlaceholderBlock(block) ? (
                  <span className="pointer-events-none absolute bottom-2.5 right-2.5 z-[1] rounded-md bg-orange-100 px-2 py-0.5 text-[11px] font-bold text-orange-800">заготовка</span>
                ) : null}
                {!block.enabled ? (
                  <span className="pointer-events-none absolute right-2.5 top-2.5 z-[1] rounded-md bg-ink px-2 py-0.5 text-[11px] font-bold text-white">скрыт на сайте</span>
                ) : null}
                <button
                  type="button"
                  onClick={() => onSelect(block.id)}
                  aria-label={`Открыть поля блока «${title}»`}
                  className={cn('block w-full text-left', !block.enabled && 'opacity-45')}
                >
                  <BlockPreview block={block} />
                </button>
                {selected ? (
                  <div className="border-t border-brand-100 bg-brand-50 px-3 py-2 dark:border-brand-900 dark:bg-brand-950/30">
                    <button
                      type="button"
                      onClick={() => onInsertAfter(block.id)}
                      className="flex h-9 w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-brand-400 bg-white text-[13px] font-semibold text-brand-700 dark:bg-slate-900"
                    >
                      <NavIcon name="plus" size={14} strokeWidth={2} />
                      Добавить блок после «{title}»
                    </button>
                  </div>
                ) : null}
              </div>
            )
          })}
          {hasInlineForm ? null : (
            <div className="border-t border-dashed border-slate-200 opacity-70" title="Общая форма заявки выводится внизу каждой страницы, если среди блоков нет своей формы">
              <span className="block px-8 pt-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Общая форма внизу страницы</span>
              <LeadFormPreview title="" />
            </div>
          )}
        </div>
      </div>
    </section>
  )
}

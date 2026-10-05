import * as DropdownMenu from '@radix-ui/react-dropdown-menu'
import type { ReactNode } from 'react'
import { cn } from '../../shared/lib/cn'
import type { ContentPageItem } from '../../types/api'

export interface PageRowActions {
  edit: (page: ContentPageItem) => void
  openOnSite: (page: ContentPageItem) => void
  preview: (page: ContentPageItem) => void
  duplicate?: (page: ContentPageItem) => void
  unpublish?: (page: ContentPageItem) => void
  archive?: (page: ContentPageItem) => void
}

const icon = (path: ReactNode) => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" className="shrink-0 text-graphite dark:text-slate-400">{path}</svg>
)

const ICONS = {
  edit: icon(<><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z" /><path d="M13.5 6.5l4 4" /></>),
  site: icon(<><path d="M14 4.5h5.5V10" /><path d="M19.5 4.5L11 13" /><path d="M18 14v4.5a1 1 0 0 1-1 1H5.5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1H10" /></>),
  preview: icon(<><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" /><circle cx="12" cy="12" r="2.8" /></>),
  copy: icon(<><rect x="8.5" y="8.5" width="11" height="11" rx="2" /><path d="M15.5 8.5V6a1.5 1.5 0 0 0-1.5-1.5H6A1.5 1.5 0 0 0 4.5 6v8A1.5 1.5 0 0 0 6 15.5h2.5" /></>),
  unpublish: icon(<><path d="M3 3l18 18" /><path d="M10.6 5.6A9.7 9.7 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-2.6 3.4M6.6 6.6C4 8.3 2.5 12 2.5 12S6 18.5 12 18.5a9 9 0 0 0 4.4-1.1" /></>),
  archive: icon(<><rect x="3.5" y="4.5" width="17" height="4.5" rx="1" /><path d="M5 9v9.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9" /><path d="M10 13h4" /></>),
}

const itemClass = 'flex min-h-[38px] cursor-pointer items-center gap-2.5 rounded-lg px-2.5 text-sm text-graphite outline-hidden data-[highlighted]:bg-surface-strong dark:text-slate-200 dark:data-[highlighted]:bg-slate-800'

/** Меню «⋯» строки списка страниц: редактирование, просмотр, копия и смена статуса. */
export function PageRowMenu({ page, actions, triggerClassName }: { page: ContentPageItem, actions: PageRowActions, triggerClassName?: string }) {
  const isPublished = page.status === 'published'
  const canArchive = actions.archive !== undefined && (page.status === 'published' || page.status === 'unpublished')
  const canUnpublish = actions.unpublish !== undefined && isPublished
  const item = (key: keyof typeof ICONS, label: string, onSelect: () => void) => (
    <DropdownMenu.Item className={itemClass} onSelect={onSelect}>{ICONS[key]}{label}</DropdownMenu.Item>
  )

  return (
    <DropdownMenu.Root modal={false}>
      <DropdownMenu.Trigger asChild>
        <button
          type="button"
          aria-label={`Действия со страницей «${page.title}»`}
          className={cn('flex h-9 w-9 items-center justify-center rounded-lg text-graphite hover:bg-surface-strong focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 data-[state=open]:bg-surface-strong dark:text-slate-300 dark:hover:bg-slate-800 dark:data-[state=open]:bg-slate-800', triggerClassName)}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="19" cy="12" r="1.7" /></svg>
        </button>
      </DropdownMenu.Trigger>
      <DropdownMenu.Portal>
        <DropdownMenu.Content align="end" sideOffset={4} className="z-50 w-60 rounded-xl border border-line bg-white p-1.5 shadow-[0_16px_32px_rgba(16,24,40,0.14)] dark:border-slate-700 dark:bg-slate-900">
          {item('edit', 'Редактировать', () => actions.edit(page))}
          {isPublished ? item('site', 'Открыть на сайте', () => actions.openOnSite(page)) : null}
          {!isPublished && page.status !== 'deleted' ? item('preview', 'Предпросмотр', () => actions.preview(page)) : null}
          {actions.duplicate !== undefined ? item('copy', 'Дублировать', () => actions.duplicate?.(page)) : null}
          {canUnpublish || canArchive ? <DropdownMenu.Separator className="mx-1 my-1.5 h-px bg-surface-strong dark:bg-slate-800" /> : null}
          {canUnpublish ? item('unpublish', 'Снять с публикации', () => actions.unpublish?.(page)) : null}
          {canArchive ? item('archive', 'В архив', () => actions.archive?.(page)) : null}
        </DropdownMenu.Content>
      </DropdownMenu.Portal>
    </DropdownMenu.Root>
  )
}

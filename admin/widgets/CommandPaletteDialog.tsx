import { useMemo, useState, type KeyboardEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useCommandPalette } from '../app/providers/command-palette-provider'
import type { AdminPermission } from '../entities/user/permissions'
import { adminRoutes, navGroups } from '../routes/route-config'
import { Dialog } from '../shared/ui/dialog'
import { Input } from '../shared/ui/input'
import { cn } from '../shared/lib/cn'
import { useAuthStore } from '../stores/auth'

export interface PaletteItem {
  id: string
  title: string
  hint: string
  path: string
  keywords: string[]
  permission?: AdminPermission
}

const ACTIONS: PaletteItem[] = [
  { id: 'action-page-new', title: 'Создать страницу', hint: 'Действие', path: '/admin/pages/new', keywords: ['новая страница', 'добавить страницу'], permission: 'pages.create' },
  { id: 'action-media-upload', title: 'Загрузить файлы в медиатеку', hint: 'Действие', path: '/admin/media', keywords: ['фото', 'изображение', 'загрузка'], permission: 'media.upload' },
  { id: 'action-redirect', title: 'Добавить редирект', hint: 'Действие', path: '/admin/seo', keywords: ['301', 'старый адрес'], permission: 'seo.edit' },
  { id: 'action-leads-new', title: 'Новые заявки', hint: 'Действие', path: '/admin/crm?status=new', keywords: ['необработанные', 'лиды'], permission: 'leads.view' },
]

const groupTitles = new Map(navGroups.map((group) => [group.key, group.title ?? 'Разделы']))

/** Пункты палитры: быстрые действия и все разделы, у которых нет параметров в адресе. */
export function buildPaletteItems(permissions?: readonly AdminPermission[]): PaletteItem[] {
  const sections = adminRoutes
    .filter((route) => !route.path.includes('/:') && route.hideInNav !== true)
    .map((route) => ({
      id: `route-${route.key}`,
      title: route.navTitle ?? route.title,
      hint: route.navGroup === undefined ? 'Раздел' : (groupTitles.get(route.navGroup) ?? 'Раздел'),
      path: route.path,
      keywords: [route.title, ...(route.keywords ?? [])],
      permission: route.permission,
    }))
  const items = [...ACTIONS, ...sections]

  return permissions === undefined
    ? items
    : items.filter((item) => item.permission === undefined || permissions.includes(item.permission))
}

export function filterPaletteItems(items: PaletteItem[], query: string): PaletteItem[] {
  const needle = query.trim().toLowerCase()
  if (needle === '') {
    return items
  }

  return items.filter((item) => [item.title, item.hint, ...item.keywords].some((text) => text.toLowerCase().includes(needle)))
}

export function CommandPaletteDialog() {
  const { isOpen, close } = useCommandPalette()
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const [activeIndex, setActiveIndex] = useState(0)
  const permissions = useAuthStore((state) => state.permissions)
  const results = useMemo(() => filterPaletteItems(buildPaletteItems(permissions), query), [permissions, query])
  const safeIndex = Math.min(activeIndex, Math.max(results.length - 1, 0))

  const reset = () => {
    setQuery('')
    setActiveIndex(0)
  }

  const go = (item: PaletteItem | undefined) => {
    if (item === undefined) {
      return
    }
    navigate(item.path)
    close()
    reset()
  }

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActiveIndex((safeIndex + 1) % Math.max(results.length, 1))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActiveIndex((safeIndex - 1 + results.length) % Math.max(results.length, 1))
    } else if (event.key === 'Enter') {
      event.preventDefault()
      go(results[safeIndex])
    }
  }

  return (
    <Dialog
      open={isOpen}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          close()
          reset()
        }
      }}
      title="Поиск и команды"
      description="Перейдите в раздел или выполните действие. ↑ ↓ — выбор, Enter — открыть."
      contentClassName="max-w-xl"
    >
      <div className="grid gap-3">
        <Input
          type="search"
          aria-label="Найти раздел или команду"
          aria-controls="command-palette-results"
          aria-activedescendant={results[safeIndex] === undefined ? undefined : `palette-${results[safeIndex].id}`}
          placeholder="Например: заявки, редирект, бэкапы"
          value={query}
          autoFocus
          onChange={(event) => {
            setQuery(event.target.value)
            setActiveIndex(0)
          }}
          onKeyDown={onKeyDown}
        />
        {results.length === 0 ? (
          <p className="px-1 py-6 text-center text-sm text-graphite dark:text-slate-400">Ничего не найдено</p>
        ) : (
          <ul id="command-palette-results" role="listbox" aria-label="Результаты" className="max-h-80 overflow-y-auto">
            {results.map((item, index) => (
              <li key={item.id} id={`palette-${item.id}`} role="option" aria-selected={index === safeIndex}>
                <button
                  type="button"
                  tabIndex={-1}
                  onMouseEnter={() => setActiveIndex(index)}
                  onClick={() => go(item)}
                  className={cn(
                    'flex min-h-10 w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm',
                    index === safeIndex ? 'bg-surface-strong dark:bg-slate-800' : 'hover:bg-surface dark:hover:bg-slate-800/60',
                  )}
                >
                  <span className="flex-1 font-medium">{item.title}</span>
                  <span className="text-xs text-graphite dark:text-slate-400">{item.hint}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Dialog>
  )
}

import type { Ref } from 'react'
import { useTheme } from '../app/providers/theme-provider'
import { useCommandPalette } from '../app/providers/command-palette-provider'
import { useSystemOverviewQuery } from '../entities/system/api'
import { Breadcrumbs } from './Breadcrumbs'
import { NavIcon } from './nav-icons'

const ENVIRONMENT_LABELS: Record<string, string> = {
  dev: 'Локальная разработка',
  test: 'Тестовое окружение',
  staging: 'DEV-стенд',
}

/** Подпись окружения для плашки в шапке; для боевого сайта плашки нет. */
export function environmentLabel(appEnv: string | undefined): string | null {
  if (appEnv === undefined || appEnv === '' || appEnv === 'prod') {
    return null
  }

  return ENVIRONMENT_LABELS[appEnv] ?? appEnv
}

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform)

interface TopbarProps {
  onOpenNav: () => void
  /** Ссылка на контейнер, в который страницы выводят свои действия. */
  actionsRef?: Ref<HTMLDivElement>
}

export function Topbar({ onOpenNav, actionsRef }: TopbarProps) {
  const { theme, toggleTheme } = useTheme()
  const { open: openCommandPalette } = useCommandPalette()
  const overview = useSystemOverviewQuery()
  const envLabel = environmentLabel(overview.data?.environment.appEnv)
  const iconButton = 'inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'

  return (
    <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-900/95">
      <div className="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 lg:px-8">
        <button type="button" className={`${iconButton} lg:hidden`} aria-label="Открыть меню" onClick={onOpenNav}>
          <NavIcon name="menu" />
        </button>

        <Breadcrumbs />

        <button
          type="button"
          onClick={openCommandPalette}
          className="order-last flex h-10 w-full items-center gap-2 rounded-lg border border-slate-300 bg-slate-50 px-3 text-left text-sm text-slate-500 transition hover:bg-white focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500 sm:order-none sm:ml-6 sm:w-auto sm:min-w-72 sm:max-w-md sm:flex-1 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
        >
          <NavIcon name="search" />
          <span className="flex-1 truncate">Найти страницу, заявку или команду…</span>
          <kbd className="rounded-md border border-slate-300 bg-white px-1.5 py-0.5 font-sans text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
            {isMac ? '⌘K' : 'Ctrl K'}
          </kbd>
        </button>

        <div className="ml-auto flex items-center gap-2">
          {envLabel !== null ? (
            <span className="hidden rounded-md bg-[#FEF0C7] px-2 py-1 text-xs font-bold text-[#7A4A00] md:inline dark:bg-amber-900/40 dark:text-amber-200">
              {envLabel}
            </span>
          ) : null}
          <div ref={actionsRef} className="flex items-center gap-2 empty:hidden" />
          <a href="/" target="_blank" rel="noreferrer" className={iconButton} aria-label="Открыть сайт в новой вкладке" title="Открыть сайт">
            <NavIcon name="external" />
          </a>
          <button
            type="button"
            className={iconButton}
            onClick={toggleTheme}
            aria-label={theme === 'dark' ? 'Включить светлую тему' : 'Включить тёмную тему'}
            title={theme === 'dark' ? 'Светлая тема' : 'Тёмная тема'}
          >
            <NavIcon name={theme === 'dark' ? 'sun' : 'moon'} />
          </button>
        </div>
      </div>
    </header>
  )
}

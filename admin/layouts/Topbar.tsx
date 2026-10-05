import type { Ref } from 'react'
import { useTheme } from '../app/providers/theme-provider'
import { useCommandPalette } from '../app/providers/command-palette-provider'
import { Link } from 'react-router-dom'
import { useLeadSummaryQuery } from '../entities/lead/api'
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
    const leadSummary = useLeadSummaryQuery()
    const newLeads = leadSummary.data?.new ?? 0
    const envLabel = environmentLabel(overview.data?.environment.appEnv)
    const iconButton =
        'inline-flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-white text-ink transition hover:bg-surface focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'

    return (
        <header className="sticky top-0 z-30 border-b border-line bg-white/95 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-900/95">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 lg:px-8">
                <button
                    type="button"
                    className={`${iconButton} lg:hidden`}
                    aria-label="Открыть меню"
                    onClick={onOpenNav}
                >
                    <NavIcon name="menu" />
                </button>

                <Breadcrumbs />

                <button
                    type="button"
                    onClick={openCommandPalette}
                    className="order-last flex h-10 w-full items-center gap-2 rounded-lg border border-line-strong bg-surface px-3 text-left text-sm text-graphite transition hover:bg-white focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 sm:order-none sm:ml-6 sm:w-auto sm:min-w-72 sm:max-w-md sm:flex-1 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                >
                    <NavIcon name="search" />
                    <span className="flex-1 truncate">Найти страницу, заявку или команду…</span>
                    <kbd className="rounded-md border border-line-strong bg-white px-1.5 py-0.5 font-sans text-xs text-graphite dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                        {isMac ? '⌘K' : 'Ctrl K'}
                    </kbd>
                </button>

                <div className="ml-auto flex items-center gap-2">
                    {envLabel !== null ? (
                        <span className="hidden rounded-md bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800 md:inline dark:bg-amber-900/40 dark:text-amber-200">
                            {envLabel}
                        </span>
                    ) : null}
                    <a
                        href="/"
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex h-10 items-center gap-1.5 rounded-[10px] px-3 font-medium text-graphite transition hover:bg-surface-strong focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 dark:text-slate-200 dark:hover:bg-slate-800"
                        aria-label="Открыть сайт в новой вкладке"
                        title="Открыть сайт"
                    >
                        <NavIcon name="external" size={16} />
                        <span className="hidden lg:inline">Открыть сайт</span>
                    </a>
                    <button
                        type="button"
                        className={iconButton}
                        onClick={toggleTheme}
                        aria-label={
                            theme === 'dark' ? 'Включить светлую тему' : 'Включить тёмную тему'
                        }
                        title={theme === 'dark' ? 'Светлая тема' : 'Тёмная тема'}
                    >
                        <NavIcon name={theme === 'dark' ? 'sun' : 'moon'} />
                    </button>
                    {leadSummary.isSuccess ? (
                        <Link
                            to="/admin/crm?status=new"
                            className={`${iconButton} relative`}
                            aria-label={
                                newLeads > 0
                                    ? `Уведомления: ${newLeads} новых`
                                    : 'Уведомления: новых нет'
                            }
                            title={newLeads > 0 ? `Новых заявок: ${newLeads}` : 'Новых заявок нет'}
                        >
                            <NavIcon name="bell" />
                            {newLeads > 0 ? (
                                <span
                                    aria-hidden="true"
                                    className="absolute right-[9px] top-2 h-2 w-2 rounded-full border-2 border-white bg-orange-700 box-content dark:border-slate-900"
                                />
                            ) : null}
                        </Link>
                    ) : null}
                    <div ref={actionsRef} className="flex items-center gap-2 empty:hidden" />
                </div>
            </div>
        </header>
    )
}

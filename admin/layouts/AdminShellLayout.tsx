import { useEffect, useState } from 'react'
import { Link, matchPath, Outlet, useLocation } from 'react-router-dom'
import { useAuthStore, useCan } from '../stores/auth'
import { CommandPaletteDialog } from '../widgets/CommandPaletteDialog'
import AssetBuildWidget from '../components/AssetBuildWidget'
import { adminRoutes } from '../routes/route-config'
import { cn } from '../shared/lib/cn'
import { NavIcon } from './nav-icons'
import { SidebarNav } from './SidebarNav'
import { Topbar } from './Topbar'
import { TopbarSlotContext } from './topbar-slot'

const SIDEBAR_COLLAPSED_STORAGE_KEY = 'admin.sidebar.collapsed'

function readCollapsed(): boolean {
  try {
    return window.localStorage.getItem(SIDEBAR_COLLAPSED_STORAGE_KEY) === '1'
  } catch {
    return false
  }
}

interface SidebarContentProps {
  collapsed: boolean
  onToggleCollapsed?: () => void
  onClose?: () => void
}

function SidebarContent({ collapsed, onToggleCollapsed, onClose }: SidebarContentProps) {
  const userEmail = useAuthStore((state) => state.userEmail)
  const userName = useAuthStore((state) => state.userName).trim()
  const logoutUrl = useAuthStore((state) => state.logoutUrl)
  const logoutToken = useAuthStore((state) => state.logoutToken)
  const userLabel = userName === '' ? userEmail.trim() : userName
  const userInitial = userLabel === '' ? '?' : userLabel.charAt(0).toUpperCase()

  return (
    <div className="flex h-full flex-col">
      <div className={cn('flex items-center gap-3 px-3 pb-3 pt-4', collapsed && 'flex-col px-2')}>
        <Link
          to="/admin/dashboard"
          onClick={onClose}
          aria-label="ЗаборПрофиль — сводка"
          className="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500"
        >
          <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-700 text-sm font-bold text-white">ЗП</span>
          {collapsed ? null : (
            <span className="min-w-0">
              <span className="block truncate text-[15px] font-bold">ЗаборПрофиль</span>
              <span className="block truncate text-xs text-slate-500 dark:text-slate-400">Панель управления</span>
            </span>
          )}
        </Link>
        {onToggleCollapsed !== undefined ? (
          <button
            type="button"
            onClick={onToggleCollapsed}
            aria-label={collapsed ? 'Развернуть меню' : 'Свернуть меню'}
            title={collapsed ? 'Развернуть меню' : 'Свернуть меню'}
            className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500 dark:text-slate-400 dark:hover:bg-slate-800"
          >
            <NavIcon name="sidebar" />
          </button>
        ) : null}
        {onClose !== undefined ? (
          <button
            type="button"
            onClick={onClose}
            aria-label="Закрыть меню"
            className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
          >
            <NavIcon name="close" />
          </button>
        ) : null}
      </div>

      <div className="flex-1 overflow-y-auto px-3 pb-4">
        <SidebarNav collapsed={collapsed} onNavigate={onClose} />
      </div>

      <div className="border-t border-slate-200 p-3 dark:border-slate-800">
        <div className={cn('flex items-center gap-3 rounded-xl', collapsed ? 'flex-col' : 'px-1')}>
          <span
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
            title={userEmail}
            aria-hidden="true"
          >
            {userInitial}
          </span>
          {collapsed ? null : (
            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm font-medium">{userLabel}</span>
              {userName === '' ? null : <span className="block truncate text-xs text-slate-500 dark:text-slate-400">{userEmail}</span>}
            </span>
          )}
          <form method="post" action={logoutUrl}>
            <input type="hidden" name="_csrf_token" value={logoutToken} />
            <button
              type="submit"
              aria-label="Выйти"
              title="Выйти"
              className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500 dark:text-slate-400 dark:hover:bg-slate-800"
            >
              <NavIcon name="logout" />
            </button>
          </form>
        </div>
      </div>
    </div>
  )
}

function isFullBleedPath(pathname: string): boolean {
  return adminRoutes.some((route) => route.fullBleed === true && matchPath({ path: route.path, end: true }, pathname) !== null)
}

export function AdminShellLayout() {
  const canViewSystem = useCan('system.view')
  const { pathname } = useLocation()
  const fullBleed = isFullBleedPath(pathname)
  const [topbarSlot, setTopbarSlot] = useState<HTMLDivElement | null>(null)
  const [collapsed, setCollapsed] = useState(readCollapsed)
  const [mobileNavOpen, setMobileNavOpen] = useState(false)

  useEffect(() => {
    try {
      window.localStorage.setItem(SIDEBAR_COLLAPSED_STORAGE_KEY, collapsed ? '1' : '0')
    } catch {
      // localStorage недоступен — состояние меню просто не запоминается.
    }
  }, [collapsed])

  useEffect(() => {
    if (!mobileNavOpen) {
      return undefined
    }
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setMobileNavOpen(false)
      }
    }
    window.addEventListener('keydown', onKeyDown)

    return () => window.removeEventListener('keydown', onKeyDown)
  }, [mobileNavOpen])

  return (
    <div className="flex min-h-screen bg-[#F6F7F9] font-[Onest,system-ui,sans-serif] text-[14px] leading-[1.45] text-[#101828] dark:bg-slate-950 dark:text-slate-100">
      <aside
        aria-label="Боковое меню"
        className={cn(
          'sticky top-0 hidden h-screen shrink-0 border-r border-slate-200 bg-white transition-[width] duration-200 lg:block dark:border-slate-800 dark:bg-slate-900',
          collapsed ? 'w-[76px]' : 'w-64',
        )}
      >
        <SidebarContent collapsed={collapsed} onToggleCollapsed={() => setCollapsed((value) => !value)} />
      </aside>

      {mobileNavOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden">
          <button
            type="button"
            aria-label="Закрыть меню"
            className="absolute inset-0 h-full w-full bg-slate-950/40"
            onClick={() => setMobileNavOpen(false)}
          />
          <aside aria-label="Боковое меню" className="relative h-full w-72 max-w-[85vw] bg-white shadow-xl dark:bg-slate-900">
            <SidebarContent collapsed={false} onClose={() => setMobileNavOpen(false)} />
          </aside>
        </div>
      ) : null}

      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar onOpenNav={() => setMobileNavOpen(true)} actionsRef={setTopbarSlot} />
        <TopbarSlotContext.Provider value={topbarSlot}>
          {fullBleed ? (
            <main className="flex min-w-0 flex-1 flex-col">
              <Outlet />
            </main>
          ) : (
            <main className="flex-1 px-4 py-6 lg:px-8">
              <section className="mx-auto max-w-[1440px] rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <Outlet />
              </section>
            </main>
          )}
        </TopbarSlotContext.Provider>
      </div>

      <CommandPaletteDialog />
      {canViewSystem ? <AssetBuildWidget /> : null}
    </div>
  )
}

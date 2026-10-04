import { useEffect, useState } from 'react'
import { NavLink, matchPath, useLocation } from 'react-router-dom'
import { useLeadSummaryQuery } from '../entities/lead/api'
import { navGroups, routesInNavGroup, type AdminRouteDefinition } from '../routes/route-config'
import { prefetchRouteByPath } from '../routes/prefetch'
import { cn } from '../shared/lib/cn'
import { NavIcon } from './nav-icons'

const SERVER_OPEN_STORAGE_KEY = 'admin.nav.serverOpen'
/** Разделы, у которых есть вложенные экраны (карточка заявки, редактор страницы): подсвечиваются и на них. */
const PREFIX_ACTIVE_KEYS = new Set(['crm', 'pages', 'seo'])

function readServerOpen(): boolean {
  try {
    return window.localStorage.getItem(SERVER_OPEN_STORAGE_KEY) === '1'
  } catch {
    return false
  }
}

function prefetchHandlers(path: string) {
  return {
    onMouseEnter: () => prefetchRouteByPath(path),
    onFocus: () => prefetchRouteByPath(path),
    onTouchStart: () => prefetchRouteByPath(path),
  }
}

interface SidebarNavProps {
  /** Узкая рейка только с иконками. */
  collapsed?: boolean
  onNavigate?: () => void
}

export function SidebarNav({ collapsed = false, onNavigate }: SidebarNavProps) {
  const location = useLocation()
  const leadSummary = useLeadSummaryQuery()
  const newLeads = leadSummary.data?.new ?? 0
  const serverRoutes = routesInNavGroup('server')
  const inServerSection = serverRoutes.some((route) => matchPath({ path: route.path, end: true }, location.pathname) !== null)
  const [serverOpen, setServerOpen] = useState(() => readServerOpen() || inServerSection)
  const [wasInServerSection, setWasInServerSection] = useState(inServerSection)
  if (wasInServerSection !== inServerSection) {
    // Перешли в технический раздел (например, из палитры команд) — раскрываем группу, чтобы был виден активный пункт.
    setWasInServerSection(inServerSection)
    if (inServerSection) {
      setServerOpen(true)
    }
  }
  const serverExpanded = serverOpen

  useEffect(() => {
    try {
      window.localStorage.setItem(SERVER_OPEN_STORAGE_KEY, serverOpen ? '1' : '0')
    } catch {
      // localStorage недоступен в приватном режиме — состояние просто не запоминается.
    }
  }, [serverOpen])

  const badgeFor = (route: AdminRouteDefinition): number => (route.key === 'crm' ? newLeads : 0)

  const renderItem = (route: AdminRouteDefinition, nested = false) => {
    const label = route.navTitle ?? route.title
    const badge = badgeFor(route)

    return (
      <NavLink
        key={route.key}
        to={route.path}
        end={!PREFIX_ACTIVE_KEYS.has(route.key)}
        aria-label={collapsed ? (badge > 0 ? `${label}: новых ${badge}` : label) : undefined}
        title={collapsed ? label : undefined}
        onClick={onNavigate}
        {...prefetchHandlers(route.path)}
        className={({ isActive }) => cn(
          'group relative flex items-center gap-3 rounded-lg text-sm font-medium transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500',
          collapsed ? 'h-11 justify-center' : nested ? 'min-h-9 px-3 py-1.5' : 'min-h-10 px-3 py-2',
          isActive
            ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
            : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
        )}
      >
        {route.icon !== undefined ? <NavIcon name={route.icon} /> : null}
        {collapsed ? null : <span className="min-w-0 flex-1 truncate">{label}</span>}
        {badge > 0 ? (
          <span
            className={cn(
              'inline-flex min-w-5 items-center justify-center rounded-full bg-orange-700 px-1.5 text-xs font-bold text-white',
              collapsed ? 'absolute right-1 top-1 h-4 min-w-4 px-1 text-[10px]' : 'h-5',
            )}
            aria-hidden={collapsed ? 'true' : undefined}
          >
            {badge}
          </span>
        ) : null}
      </NavLink>
    )
  }

  return (
    <nav aria-label="Разделы админки" className="flex flex-col gap-0.5">
      {navGroups.map((group) => {
        const routes = routesInNavGroup(group.key)
        if (routes.length === 0) {
          return null
        }

        if (group.collapsible === true) {
          if (collapsed) {
            const first = routes[0]!

            return (
              <NavLink
                key={group.key}
                to={first.path}
                end
                aria-label={group.title ?? first.title}
                title={group.title ?? first.title}
                onClick={onNavigate}
                className={cn(
                  'mt-3 flex h-11 items-center justify-center rounded-lg transition hover:bg-slate-100 dark:hover:bg-slate-800',
                  inServerSection ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'text-slate-700 dark:text-slate-300',
                )}
              >
                <NavIcon name={group.icon ?? 'server'} />
              </NavLink>
            )
          }

          return (
            <div key={group.key} className="mt-4 border-t border-slate-200 pt-3 dark:border-slate-800">
              <button
                type="button"
                aria-expanded={serverExpanded}
                aria-controls={`nav-group-${group.key}`}
                onClick={() => setServerOpen(!serverExpanded)}
                className="flex min-h-10 w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500 dark:text-slate-300 dark:hover:bg-slate-800"
              >
                <NavIcon name={group.icon ?? 'server'} />
                <span className="flex-1">{group.title}</span>
                <NavIcon name="chevron" size={16} className={cn('transition-transform', serverExpanded && 'rotate-180')} />
              </button>
              {serverExpanded ? (
                <div id={`nav-group-${group.key}`} className="ml-4 mt-0.5 flex flex-col gap-0.5 border-l border-slate-200 pl-2 dark:border-slate-800">
                  {routes.map((route) => renderItem(route, true))}
                </div>
              ) : null}
            </div>
          )
        }

        return (
          <div key={group.key} className={cn('flex flex-col gap-0.5', group.title !== null && 'mt-4')}>
            {group.title !== null && !collapsed ? (
              <p className="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{group.title}</p>
            ) : null}
            {group.title !== null && collapsed ? <span className="mx-3 mb-1 border-t border-slate-200 dark:border-slate-800" aria-hidden="true" /> : null}
            {routes.map((route) => renderItem(route))}
          </div>
        )
      })}
    </nav>
  )
}

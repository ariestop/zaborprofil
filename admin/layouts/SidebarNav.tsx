import { NavLink } from 'react-router-dom'
import { sidebarRoutes } from '../routes/route-config'
import { prefetchRouteByPath } from '../routes/prefetch'

export function SidebarNav() {
  return (
    <aside className="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <nav className="flex flex-wrap gap-2">
        {sidebarRoutes.map((route) => (
          <NavLink
            key={route.key}
            to={route.path}
            onMouseEnter={() => prefetchRouteByPath(route.path)}
            onFocus={() => prefetchRouteByPath(route.path)}
            onTouchStart={() => prefetchRouteByPath(route.path)}
            className={({ isActive }) => [
              'rounded-lg px-3 py-2 text-sm font-medium transition',
              isActive
                ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800',
            ].join(' ')}
          >
            {route.navTitle ?? route.title}
          </NavLink>
        ))}
      </nav>
    </aside>
  )
}

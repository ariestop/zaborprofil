import { NavLink } from 'react-router-dom'
import { sidebarRoutes } from '../routes/route-config'
import { prefetchRouteByPath } from '../routes/prefetch'

export function SidebarNav() {
  const parentRoutes = sidebarRoutes.filter((route) => route.parentKey === undefined)
  const childRoutesByParent = new Map(
    parentRoutes.map((parentRoute) => [
      parentRoute.key,
      sidebarRoutes.filter((route) => route.parentKey === parentRoute.key),
    ]),
  )

  return (
    <aside className="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <nav className="space-y-1">
        {parentRoutes.map((route) => {
          const childRoutes = childRoutesByParent.get(route.key) ?? []

          return (
            <div key={route.key} className="space-y-1">
              <NavLink
                to={route.path}
                onMouseEnter={() => prefetchRouteByPath(route.path)}
                onFocus={() => prefetchRouteByPath(route.path)}
                onTouchStart={() => prefetchRouteByPath(route.path)}
                className={({ isActive }) => [
                  'block rounded-lg px-3 py-2 text-sm font-medium transition',
                  isActive
                    ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                    : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800',
                ].join(' ')}
              >
                {route.navTitle ?? route.title}
              </NavLink>
              {childRoutes.length > 0 ? (
                <div className="ml-4 space-y-1 border-l border-slate-200 pl-2 dark:border-slate-700">
                  {childRoutes.map((childRoute) => (
                    <NavLink
                      key={childRoute.key}
                      to={childRoute.path}
                      onMouseEnter={() => prefetchRouteByPath(childRoute.path)}
                      onFocus={() => prefetchRouteByPath(childRoute.path)}
                      onTouchStart={() => prefetchRouteByPath(childRoute.path)}
                      className={({ isActive }) => [
                        'block rounded-lg px-3 py-2 text-sm transition',
                        isActive
                          ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                          : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800',
                      ].join(' ')}
                    >
                      {childRoute.navTitle ?? childRoute.title}
                    </NavLink>
                  ))}
                </div>
              ) : null}
            </div>
          )
        })}
      </nav>
    </aside>
  )
}

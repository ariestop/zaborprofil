import { Link, matchPath, useLocation } from 'react-router-dom'
import { adminRoutes, type AdminRouteDefinition } from '../routes/route-config'

function routeForPath(pathname: string): AdminRouteDefinition | null {
  for (const route of adminRoutes) {
    if (matchPath({ path: route.path, end: true }, pathname) !== null) {
      return route
    }
  }

  return null
}

export function Breadcrumbs() {
  const location = useLocation()
  const route = routeForPath(location.pathname)
  const parent = route?.parentKey === undefined ? null : (adminRoutes.find((item) => item.key === route.parentKey) ?? null)
  const title = route === null ? 'Сводка' : (route.navTitle ?? route.title)

  return (
    <nav aria-label="Хлебные крошки" className="min-w-0 text-sm">
      <ol className="flex min-w-0 items-center gap-2">
        {parent !== null ? (
          <>
            <li className="shrink-0">
              <Link to={parent.path} className="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                {parent.navTitle ?? parent.title}
              </Link>
            </li>
            <li aria-hidden="true" className="text-slate-400">/</li>
          </>
        ) : null}
        <li aria-current="page" className="truncate font-semibold text-slate-900 dark:text-slate-100">{title}</li>
      </ol>
    </nav>
  )
}

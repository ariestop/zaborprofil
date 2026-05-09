import { Link, matchPath, useLocation } from 'react-router-dom'
import { adminRoutes } from '../routes/route-config'

function labelForPath(pathname: string): string {
  for (const route of adminRoutes) {
    if (matchPath({ path: route.path, end: true }, pathname) !== null) {
      return route.title
    }
  }

  return 'Панель управления'
}

export function Breadcrumbs() {
  const location = useLocation()
  const title = labelForPath(location.pathname)

  return (
    <nav aria-label="breadcrumbs" className="text-sm text-slate-500 dark:text-slate-400">
      <ol className="flex items-center gap-2">
        <li>
          <Link to="/admin/dashboard" className="hover:text-slate-800 dark:hover:text-slate-100">
            Админка
          </Link>
        </li>
        <li>/</li>
        <li className="text-slate-900 dark:text-slate-200">{title}</li>
      </ol>
    </nav>
  )
}

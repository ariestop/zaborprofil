import { useEffect, useMemo, useState } from 'react'
import { routeNameByPath, routes, type AdminRouteName } from '../router'
import { useAuthStore } from '../stores/auth'
import AssetBuildWidget from './AssetBuildWidget'
import AuditLogView from '../views/AuditLogView'
import ContentPagesView from '../views/ContentPagesView'
import DashboardView from '../views/DashboardView'
import LeadsView from '../views/LeadsView'
import MaintenanceView from '../views/MaintenanceView'
import MediaLibraryView from '../views/MediaLibraryView'
import MenuView from '../views/MenuView'
import MigrationsView from '../views/MigrationsView'
import RedirectsView from '../views/RedirectsView'
import SettingsView from '../views/SettingsView'
import SystemHealthView from '../views/SystemHealthView'

function routeElement(routeName: AdminRouteName) {
  if (routeName === 'dashboard') return <DashboardView />
  if (routeName === 'content') return <ContentPagesView />
  if (routeName === 'media') return <MediaLibraryView />
  if (routeName === 'menu') return <MenuView />
  if (routeName === 'leads') return <LeadsView />
  if (routeName === 'settings') return <SettingsView />
  if (routeName === 'settingsMigrations') return <MigrationsView />
  if (routeName === 'redirects') return <RedirectsView />
  if (routeName === 'systemHealth') return <SystemHealthView />
  if (routeName === 'maintenance') return <MaintenanceView />
  if (routeName === 'auditLog') return <AuditLogView />

  return <DashboardView />
}

function routeFromPath(pathname: string): AdminRouteName | null {
  if (pathname === '/admin') {
    return 'dashboard'
  }

  const route = routes.find((item) => item.path === pathname)
  return route?.name ?? null
}

export default function AdminShell() {
  const userEmail = useAuthStore((state) => state.userEmail)
  const logoutUrl = useAuthStore((state) => state.logoutUrl)
  const logoutToken = useAuthStore((state) => state.logoutToken)
  const [pathname, setPathname] = useState(() => window.location.pathname)
  const matchedRouteName = routeFromPath(pathname)
  const routeName = matchedRouteName ?? routeNameByPath('/admin/dashboard')
  const pageTitle = useMemo(() => (
    routes.find((route) => route.name === routeName)?.title ?? 'Панель управления'
  ), [routeName])

  const navigateTo = (path: string): void => {
    if (window.location.pathname === path) {
      return
    }

    window.history.pushState({}, '', path)
    setPathname(path)
  }

  useEffect(() => {
    const handlePopState = () => {
      setPathname(window.location.pathname)
    }

    window.addEventListener('popstate', handlePopState)

    return () => {
      window.removeEventListener('popstate', handlePopState)
    }
  }, [])

  useEffect(() => {
    if (matchedRouteName !== null) {
      return
    }

    navigateTo('/admin/dashboard')
  }, [matchedRouteName])

  const isRouteActive = (routeNameToCheck: AdminRouteName): boolean => {
    const route = routes.find((item) => item.name === routeNameToCheck)
    return routeName === routeNameToCheck || route?.parentName === routeNameToCheck
  }

  return (
    <div className="min-h-screen">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">Zaborprofil CMS</p>
            <h1 className="text-xl font-bold text-slate-950">{pageTitle}</h1>
          </div>
          <div className="flex items-center gap-4">
            <span className="text-sm text-slate-600">{userEmail}</span>
            <form method="post" action={logoutUrl}>
              <input type="hidden" name="_csrf_token" value={logoutToken} />
              <button
                type="submit"
                className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
              >
                Выйти
              </button>
            </form>
          </div>
        </div>
      </header>

      <div className="mx-auto grid max-w-7xl grid-cols-[240px_1fr] gap-6 px-6 py-8">
        <aside className="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
          <nav className="space-y-1">
            {routes.map((route) => (
              <button
                key={route.name}
                type="button"
                className={[
                  'flex w-full rounded-lg py-2 text-left font-medium transition',
                  route.parentName ? 'px-6 text-xs' : 'px-3 text-sm',
                  isRouteActive(route.name) ? 'bg-emerald-50 text-emerald-800' : 'text-slate-700 hover:bg-slate-50',
                ].join(' ')}
                onClick={() => navigateTo(route.path)}
              >
                {route.navTitle ?? route.title}
              </button>
            ))}
          </nav>
        </aside>

        <main>{routeElement(routeName)}</main>
      </div>

      <AssetBuildWidget />
    </div>
  )
}

export type AdminRouteName = 'dashboard' | 'settings' | 'redirects' | 'systemHealth' | 'maintenance' | 'auditLog'

export interface AdminRoute {
  name: AdminRouteName
  path: string
  title: string
}

export const routes: AdminRoute[] = [
  { name: 'dashboard', path: '/admin/dashboard', title: 'Панель управления' },
  { name: 'settings', path: '/admin/settings', title: 'Настройки' },
  { name: 'redirects', path: '/admin/seo/redirects', title: 'Редиректы' },
  { name: 'systemHealth', path: '/admin/system/health', title: 'Health Center' },
  { name: 'maintenance', path: '/admin/system/maintenance', title: 'Maintenance' },
  { name: 'auditLog', path: '/admin/system/audit', title: 'Audit Log' },
]

export function currentRouteName(pathname = window.location.pathname): AdminRouteName {
  return routes.find((route) => route.path === pathname)?.name ?? 'dashboard'
}

export function navigateTo(path: string): void {
  if (window.location.pathname === path) {
    return
  }

  window.history.pushState({}, '', path)
  window.dispatchEvent(new PopStateEvent('popstate'))
}
